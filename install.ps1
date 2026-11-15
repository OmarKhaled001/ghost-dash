<#
.SYNOPSIS
    GhostDash one-command installer for Windows.

.DESCRIPTION
    irm https://raw.githubusercontent.com/omarkhaled001/ghost-dash/main/install.ps1 | iex

    The script asks for the project name. To skip the question, or to pass
    options to the installer, set these first:

        $env:GHOSTDASH_APP  = 'my-app'
        $env:GHOSTDASH_ARGS = '--db-username=root --create-db --no-git'

    When run from a file, the same can be given as arguments:

        .\install.ps1 my-app --create-db

    All real work is done by `php artisan dash:install`. This script only
    gets a machine to the point where that command can run:

      1. print what will be installed
      2. check PHP, Composer, Node.js, npm and git, and say how to fix each gap
      3. composer create-project
      4. hand over to php artisan dash:install

    Environment:
      GHOSTDASH_VERSION   version to install (default: latest stable; dev-main works)
      GHOSTDASH_SOURCE    path to a local checkout to install from instead of Packagist

    It never installs anything without asking, and never deletes a file of
    yours. Works on Windows PowerShell 5.1 and PowerShell 7, which is why
    it avoids newer syntax such as the ternary operator and `&&`.
#>

$ErrorActionPreference = 'Stop'

# Everything lives in functions that are called from the last line, so that
# a download cut off half way cannot execute half a script.

function Write-Line {
    [Diagnostics.CodeAnalysis.SuppressMessageAttribute('PSAvoidUsingWriteHost', '', Justification = 'An interactive installer writes to the console, not to the pipeline.')]
    param(
        [string] $Text = '',
        [string] $Color = ''
    )

    if ($Color -ne '') {
        Write-Host $Text -ForegroundColor $Color
    }
    else {
        Write-Host $Text
    }
}

function Write-Check {
    [Diagnostics.CodeAnalysis.SuppressMessageAttribute('PSAvoidUsingWriteHost', '', Justification = 'An interactive installer writes to the console, not to the pipeline.')]
    param(
        [Parameter(Mandatory = $true)] [ValidateSet('ok', 'warn', 'fail')] [string] $Status,
        [Parameter(Mandatory = $true)] [string] $Text,
        [string[]] $Fix = @()
    )

    $color = 'Green'
    $label = 'ok   '

    if ($Status -eq 'warn') {
        $color = 'Yellow'
        $label = 'warn '
    }

    if ($Status -eq 'fail') {
        $color = 'Red'
        $label = 'fail '
        $script:Problems++
    }

    Write-Host "  $label " -ForegroundColor $color -NoNewline
    Write-Host $Text

    foreach ($line in $Fix) {
        Write-Host "         $line" -ForegroundColor DarkGray
    }
}

function Write-Banner {
    Write-Line ''
    Write-Line '   ____ _               _   ____            _     ' 'Cyan'
    Write-Line '  / ___| |__   ___  ___| |_|  _ \  __ _ ___| |__  ' 'Cyan'
    Write-Line ' | |  _| ''_ \ / _ \/ __| __| | | |/ _` / __| ''_ \ ' 'Cyan'
    Write-Line ' | |_| | | | | (_) \__ \ |_| |_| | (_| \__ \ | | |' 'Cyan'
    Write-Line '  \____|_| |_|\___/|___/\__|____/ \__,_|___/_| |_|' 'Cyan'
    Write-Line ''
    Write-Line '  Fast. Quiet. Secure. Your bilingual Laravel admin in one command.'
    Write-Line ''
}

function Test-Command {
    param([Parameter(Mandatory = $true)] [string] $Name)

    return $null -ne (Get-Command $Name -ErrorAction SilentlyContinue)
}

function Test-Interactive {
    # False in CI and whenever input is redirected: nobody can answer.
    if (-not [Environment]::UserInteractive) {
        return $false
    }

    try {
        return -not [Console]::IsInputRedirected
    }
    catch {
        return $false
    }
}

function Split-InstallerArgument {
    # Turns '--a=1 --name="Omar K" --flag' into three arguments, the way a
    # shell would, so GHOSTDASH_ARGS can carry values that contain spaces.
    param([string] $Text)

    $result = @()

    if ([string]::IsNullOrWhiteSpace($Text)) {
        return $result
    }

    foreach ($match in [regex]::Matches($Text, '(?:[^\s"'']+|"[^"]*"|''[^'']*'')+')) {
        $result += ($match.Value -replace '["'']', '')
    }

    return $result
}

function Get-VersionDescription {
    if ($env:GHOSTDASH_SOURCE) {
        return "local checkout at $($env:GHOSTDASH_SOURCE)"
    }

    if ($env:GHOSTDASH_VERSION) {
        return $env:GHOSTDASH_VERSION
    }

    # Informational only: Composer resolves the version itself. Packagist
    # lists releases newest first.
    try {
        $metadata = Invoke-RestMethod -Uri "https://repo.packagist.org/p2/$($script:Package).json" -TimeoutSec 8
        $latest = $metadata.packages.($script:Package) |
            Where-Object { $_.version -match '^v?\d+(\.\d+)*$' } |
            Select-Object -First 1

        if ($latest) {
            return "$($latest.version) (latest stable)"
        }
    }
    catch {
        Write-Verbose "Could not read the latest version from Packagist: $_"
    }

    return 'latest stable'
}

function Test-Php {
    $phpNew = "Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/$($script:PhpSeries)'))"

    if (-not (Test-Command 'php')) {
        Write-Check -Status fail -Text "PHP is not installed (need $($script:PhpSeries) or newer)" -Fix @($phpNew, 'or install Laravel Herd: https://herd.laravel.com/windows')
        $script:NeedsPhpNew = $true
        return
    }

    $version = (& php -r 'echo PHP_VERSION;')
    & php -r 'exit(PHP_VERSION_ID >= 80400 ? 0 : 1);'

    if ($LASTEXITCODE -ne 0) {
        Write-Check -Status fail -Text "PHP $version is too old (need $($script:PhpSeries) or newer)" -Fix @($phpNew, 'or install Laravel Herd: https://herd.laravel.com/windows')
        $script:NeedsPhpNew = $true
        return
    }

    Write-Check -Status ok -Text "PHP $version"

    $missing = @()

    foreach ($extension in $script:Extensions) {
        & php -r "exit(extension_loaded('$extension') ? 0 : 1);"

        if ($LASTEXITCODE -ne 0) {
            $missing += $extension
        }
    }

    if ($missing.Count -gt 0) {
        $ini = (& php -r 'echo php_ini_loaded_file();')

        if (-not $ini) {
            $ini = 'php.ini (run "php --ini" to find it)'
        }

        $lines = @("Enable them in $ini by adding or uncommenting:")

        foreach ($extension in $missing) {
            $lines += "  extension=$extension"
        }

        Write-Check -Status fail -Text "PHP extensions missing: $($missing -join ', ')" -Fix $lines
    }
    else {
        Write-Check -Status ok -Text 'PHP extensions'
    }
}

function Test-Composer {
    if (-not (Test-Command 'composer')) {
        Write-Check -Status fail -Text 'Composer is not installed (need version 2)' -Fix @('https://getcomposer.org/Composer-Setup.exe', 'or: winget install Composer.Composer')
        $script:NeedsPhpNew = $true
        return
    }

    # Composer may write notices to stderr. With ErrorActionPreference set
    # to Stop, Windows PowerShell 5.1 turns redirected stderr into a
    # terminating error, so the preference is relaxed for this one call.
    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'

    try {
        $output = (& composer --version --no-ansi 2>&1 | Out-String)
    }
    finally {
        $ErrorActionPreference = $previous
    }

    if ($output -match 'Composer version (\d+)\.([\d.]+)') {
        if ([int] $Matches[1] -ge 2) {
            Write-Check -Status ok -Text "Composer $($Matches[1]).$($Matches[2])"
            return
        }

        Write-Check -Status fail -Text "Composer $($Matches[1]) is too old (need version 2)" -Fix @('composer self-update --2')
        return
    }

    Write-Check -Status fail -Text 'Composer is installed but did not report its version' -Fix @('composer --version')
}

function Test-Node {
    $fix = @('winget install OpenJS.NodeJS.LTS', 'or: https://nodejs.org')

    if (-not (Test-Command 'node')) {
        Write-Check -Status fail -Text "Node.js is not installed (need $($script:NodeMinimum) or newer)" -Fix $fix
        return
    }

    $version = (& node -v)
    # Single quotes inside the JavaScript on purpose: Windows PowerShell 5.1
    # drops double quotes from arguments it passes to native programs.
    $major = [int] (& node -p "process.versions.node.split('.')[0]")

    if ($major -lt $script:NodeMinimum) {
        Write-Check -Status fail -Text "Node.js $version is too old (need $($script:NodeMinimum) or newer)" -Fix $fix
    }
    else {
        Write-Check -Status ok -Text "Node.js $version"
    }

    if (Test-Command 'npm') {
        Write-Check -Status ok -Text "npm $(& npm -v)"
    }
    else {
        Write-Check -Status fail -Text 'npm is not installed' -Fix $fix
    }
}

function Test-Git {
    if (Test-Command 'git') {
        Write-Check -Status ok -Text (& git --version)
    }
    else {
        Write-Check -Status fail -Text 'git is not installed' -Fix @('winget install Git.Git', 'or: https://git-scm.com/download/win')
    }
}

function Test-MysqlClient {
    if ((Test-Command 'mysql') -and (Test-Command 'mysqldump')) {
        Write-Check -Status ok -Text 'mysql client'
        return
    }

    # Not fatal: the application talks to MySQL through PHP. Only backups
    # need mysqldump.
    Write-Check -Status warn -Text 'mysql / mysqldump not found. The app will run, but backups cannot dump the database.' -Fix @(
        'They ship with MySQL Server: winget install Oracle.MySQL',
        'If MySQL is installed, add its bin folder to PATH or set DB_DUMP_BINARY_PATH in .env.'
    )
}

function Test-Target {
    param([Parameter(Mandatory = $true)] [string] $Path)

    if (-not (Test-Path -LiteralPath $Path)) {
        Write-Check -Status ok -Text "$Path is free"
        return
    }

    $item = Get-Item -LiteralPath $Path -Force

    if ($item.PSIsContainer -and @(Get-ChildItem -LiteralPath $Path -Force).Count -eq 0) {
        Write-Check -Status ok -Text "$Path exists and is empty"
        return
    }

    Write-Check -Status fail -Text "$Path already exists and is not empty" -Fix @('Choose another name, or move that folder away. This script will not delete it.')
}

function Invoke-PhpNew {
    Write-Line ''
    Write-Line "PHP $($script:PhpSeries) and Composer can be installed together by the official php.new installer."

    if (-not (Test-Interactive)) {
        return
    }

    $answer = Read-Host 'Run it now? [y/N]'

    if ($answer -notmatch '^(y|yes)$') {
        return
    }

    # Run in a child PowerShell, exactly as php.new documents it.
    $command = "Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/$($script:PhpSeries)'))"

    & powershell.exe -NoProfile -ExecutionPolicy Bypass -Command $command

    Write-Line ''
    Write-Line 'Restart your terminal, then run the GhostDash install command again.' 'White'
    Write-Line ''

    throw [System.OperationCanceledException]::new('php.new was started.')
}

function Get-LocalRepositoryFile {
    # Describes the local checkout as a tiny Composer repository in a file.
    # The usual --repository='{"type":"path",...}' is avoided on purpose:
    # Windows PowerShell 5.1 strips double quotes from arguments passed to
    # composer.bat, and Composer then receives broken JSON.
    param([Parameter(Mandatory = $true)] [string] $Source)

    $resolved = (Resolve-Path -LiteralPath $Source).Path

    if (-not (Test-Path -LiteralPath (Join-Path $resolved 'composer.json'))) {
        throw "GHOSTDASH_SOURCE ($Source) does not contain a composer.json."
    }

    $directory = Join-Path ([System.IO.Path]::GetTempPath()) ("ghostdash-" + [System.Guid]::NewGuid().ToString('N'))
    $null = New-Item -ItemType Directory -Path $directory

    $definition = @{
        packages = @{
            $script:Package = @{
                'dev-main' = @{
                    name    = $script:Package
                    version = 'dev-main'
                    type    = 'project'
                    dist    = @{
                        type = 'path'
                        url  = ($resolved -replace '\\', '/')
                    }
                }
            }
        }
    }

    $file = Join-Path $directory 'packages.json'

    # Composer rejects a UTF-8 byte order mark, which Set-Content -Encoding
    # UTF8 writes on Windows PowerShell 5.1.
    [System.IO.File]::WriteAllText($file, ($definition | ConvertTo-Json -Depth 8), (New-Object System.Text.UTF8Encoding($false)))

    return $file
}

function Invoke-Install {
    param([string[]] $Arguments = @())

    $script:Package = 'omarkhaled001/ghost-dash'
    $script:PhpSeries = '8.4'
    $script:NodeMinimum = 22
    $script:Extensions = @('bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'intl', 'mbstring', 'openssl', 'pdo_mysql', 'tokenizer', 'xml', 'zip')
    $script:Problems = 0
    $script:NeedsPhpNew = $false

    $projectCreated = $false
    $appDir = ''
    $startDirectory = (Get-Location).Path

    try {
        # Windows PowerShell 5.1 on older systems still defaults to TLS 1.0,
        # which Packagist and GitHub refuse.
        try {
            [Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12
        }
        catch {
            Write-Verbose "Could not enable TLS 1.2: $_"
        }

        Write-Banner

        # The project name is the first argument that is not an option.
        # Everything else is for dash:install.
        $forwarded = @()

        foreach ($argument in $Arguments) {
            if ($appDir -eq '' -and -not $argument.StartsWith('-')) {
                $appDir = $argument
            }
            else {
                $forwarded += $argument
            }
        }

        if ($appDir -eq '' -and $env:GHOSTDASH_APP) {
            $appDir = $env:GHOSTDASH_APP
        }

        $forwarded += Split-InstallerArgument $env:GHOSTDASH_ARGS

        if ($appDir -eq '' -and (Test-Interactive)) {
            $appDir = (Read-Host 'Project name').Trim()
        }

        if ($appDir -eq '') {
            Write-Line 'Set the project name first, then run the command again:'
            Write-Line ''
            Write-Line "  `$env:GHOSTDASH_APP = 'my-app'"
            Write-Line "  irm https://raw.githubusercontent.com/$($script:Package)/main/install.ps1 | iex"
            throw 'No project name was given.'
        }

        if ($appDir -notmatch '^[A-Za-z0-9._\\/:-]+$') {
            throw "'$appDir' is not a usable folder name. Use letters, digits, dots, dashes and underscores."
        }

        Write-Line "  Installing $($script:Package): $(Get-VersionDescription)"
        Write-Line "  Into       $appDir"
        Write-Line ''
        Write-Line 'Checking requirements' 'White'

        Test-Php
        Test-Composer
        Test-Node
        Test-Git
        Test-MysqlClient
        Test-Target $appDir

        if ($script:Problems -gt 0) {
            if ($script:NeedsPhpNew) {
                Invoke-PhpNew
            }

            Write-Line ''
            Write-Line "$($script:Problems) requirement(s) are not met. Fix them with the commands shown above, then run this again." 'Red'
            Write-Line 'Nothing was installed or changed.'
            throw 'Requirements are not met.'
        }

        Write-Line ''
        Write-Line 'Creating the project' 'White'

        $composerArguments = @('create-project', $script:Package, $appDir)

        if ($env:GHOSTDASH_SOURCE) {
            $composerArguments += @("--repository=$(Get-LocalRepositoryFile $env:GHOSTDASH_SOURCE)", '--stability=dev')
        }
        elseif ($env:GHOSTDASH_VERSION) {
            $composerArguments += $env:GHOSTDASH_VERSION
        }

        $composerArguments += '--no-interaction'

        Write-Line "> composer $($composerArguments -join ' ')" 'DarkGray'

        # The project's own post-create hook would start the installer too.
        # It is started below instead, with the options given here.
        $env:GHOSTDASH_SKIP_AUTO_INSTALL = '1'

        # Makes Composer copy a path repository even where it would rather
        # link to it. A linked "project" would be the source checkout
        # itself, and everything below would modify that.
        $env:COMPOSER_MIRROR_PATH_REPOS = '1'

        & composer @composerArguments

        if ($LASTEXITCODE -ne 0) {
            throw "composer create-project failed with exit code $LASTEXITCODE."
        }

        $created = Get-Item -LiteralPath $appDir -Force

        if ($created.Attributes -band [System.IO.FileAttributes]::ReparsePoint) {
            throw "$appDir was created as a link to the source instead of a copy. Remove the link and report this."
        }

        $projectCreated = $true
        Set-Location -LiteralPath $appDir

        Write-Line ''
        Write-Line 'Running the installer' 'White'
        # This line ends up in terminal scrollback and CI logs, so a password
        # passed as an option is not repeated in it.
        $shown = @($forwarded | ForEach-Object {
            if ($_ -match '^(--[^=]*password[^=]*)=') { $Matches[1] + '=********' } else { $_ }
        })
        Write-Line "> php artisan dash:install $($shown -join ' ')" 'DarkGray'

        & php artisan dash:install @forwarded

        if ($LASTEXITCODE -ne 0) {
            throw "php artisan dash:install stopped with exit code $LASTEXITCODE."
        }
    }
    catch [System.OperationCanceledException] {
        # php.new was started: the person has been told what to do next.
        return
    }
    catch {
        Write-Line ''
        Write-Line "Installation stopped: $($_.Exception.Message)" 'Red'

        if ($projectCreated) {
            Write-Line ''
            Write-Line 'The project was created. Fix the problem above, then continue with:'
            Write-Line ''
            Write-Line "    cd $appDir; php artisan dash:install" 'White'
            Write-Line ''
        }

        # Under `irm | iex` there is no script to exit from: `exit` would
        # close the person's terminal. Leave a failing exit code for
        # callers that check it (CI) and return.
        $global:LASTEXITCODE = 1

        if ($MyInvocation.ScriptName -or $env:CI) {
            exit 1
        }

        return
    }
    finally {
        Remove-Item Env:\GHOSTDASH_SKIP_AUTO_INSTALL -ErrorAction SilentlyContinue
        Remove-Item Env:\COMPOSER_MIRROR_PATH_REPOS -ErrorAction SilentlyContinue

        # Stay in the new project on success, so the next command just
        # works; go back where we started otherwise.
        if (-not $projectCreated) {
            Set-Location -LiteralPath $startDirectory
        }
    }
}

Invoke-Install -Arguments $args

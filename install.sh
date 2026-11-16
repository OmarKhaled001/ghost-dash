#!/usr/bin/env bash
#
# GhostDash one-command installer for macOS, Linux and WSL.
#
#   curl -fsSL https://raw.githubusercontent.com/omarkhaled001/ghost-dash/main/install.sh | bash -s -- my-app [options]
#
# Everything after the project name is passed to `php artisan dash:install`,
# which is where the real work happens. This script only gets a machine to
# the point where that command can run:
#
#   1. print what will be installed
#   2. check PHP, Composer, Node.js, npm and git, and say how to fix each gap
#   3. composer create-project
#   4. hand over to php artisan dash:install
#
# Environment:
#   GHOSTDASH_VERSION   version to install (default: latest stable; dev-main works)
#   GHOSTDASH_SOURCE    path to a local checkout to install from instead of Packagist
#
# It never installs anything without asking, and never deletes a file.

set -euo pipefail

PACKAGE="omarkhaled001/ghost-dash"
REPOSITORY="https://github.com/omarkhaled001/ghost-dash"
PHP_SERIES="8.4"
NODE_MINIMUM=22
EXTENSIONS="bcmath ctype curl dom fileinfo intl mbstring openssl pdo_mysql tokenizer xml zip"

OS=""
FAMILY=""
APP_DIR=""
PROJECT_CREATED=0
PROBLEMS=0
NEEDS_PHP_NEW=0

# --- output -----------------------------------------------------------------

if [ -t 1 ] && [ -z "${NO_COLOR:-}" ]; then
    BOLD=$'\033[1m'; DIM=$'\033[2m'; RED=$'\033[31m'; GREEN=$'\033[32m'; YELLOW=$'\033[33m'; CYAN=$'\033[36m'; RESET=$'\033[0m'
else
    BOLD=""; DIM=""; RED=""; GREEN=""; YELLOW=""; CYAN=""; RESET=""
fi

say()  { printf '%s\n' "$*"; }
ok()   { printf '  %sok%s    %s\n' "$GREEN" "$RESET" "$*"; }
warn() { printf '  %swarn%s  %s\n' "$YELLOW" "$RESET" "$*"; }
bad()  { printf '  %sfail%s  %s\n' "$RED" "$RESET" "$*"; PROBLEMS=$((PROBLEMS + 1)); }
fix()  { printf '        %s%s%s\n' "$DIM" "$*" "$RESET"; }

die() {
    printf '\n%sInstallation stopped:%s %s\n' "$RED" "$RESET" "$*" >&2
    resume_hint >&2
    exit 1
}

resume_hint() {
    if [ "$PROJECT_CREATED" -eq 1 ]; then
        printf '\nThe project was created. Fix the problem above, then continue with:\n\n    %scd %s && php artisan dash:install%s\n\n' "$BOLD" "$APP_DIR" "$RESET"
    fi
}

on_error() {
    code=$?
    printf '\n%sInstallation stopped%s (a command exited with status %s).\n' "$RED" "$RESET" "$code" >&2
    resume_hint >&2
    exit "$code"
}

banner() {
    say ""
    say "${CYAN}   ____ _               _   ____            _     ${RESET}"
    say "${CYAN}  / ___| |__   ___  ___| |_|  _ \\  __ _ ___| |__  ${RESET}"
    say "${CYAN} | |  _| '_ \\ / _ \\/ __| __| | | |/ _\` / __| '_ \\ ${RESET}"
    say "${CYAN} | |_| | | | | (_) \\__ \\ |_| |_| | (_| \\__ \\ | | |${RESET}"
    say "${CYAN}  \\____|_| |_|\\___/|___/\\__|____/ \\__,_|___/_| |_|${RESET}"
    say ""
    say "  Fast. Quiet. Secure. Your bilingual Laravel admin in one command."
    say ""
}

# --- terminal ---------------------------------------------------------------

# When this script arrives through `curl | bash`, stdin is the script itself.
# Questions therefore have to be asked on the terminal device directly.
has_tty() {
    [ -r /dev/tty ] && [ -w /dev/tty ] && (exec </dev/tty) 2>/dev/null
}

# ask_yes_no "Question" -> 0 for yes. Defaults to no, and is no without a terminal.
ask_yes_no() {
    if ! has_tty; then
        return 1
    fi

    printf '%s [y/N] ' "$1" >/dev/tty
    answer=""
    read -r answer </dev/tty || answer=""

    case "$answer" in
        y | Y | yes | YES | Yes) return 0 ;;
        *) return 1 ;;
    esac
}

# --- platform ---------------------------------------------------------------

detect_platform() {
    case "$(uname -s)" in
        Darwin)
            OS="mac"
            FAMILY="mac"
            ;;
        Linux)
            OS="linux"
            id=""
            like=""

            if [ -r /etc/os-release ]; then
                id=$(sed -n 's/^ID=//p' /etc/os-release | tr -d '"')
                like=$(sed -n 's/^ID_LIKE=//p' /etc/os-release | tr -d '"')
            fi

            case " $id $like " in
                *" debian "* | *" ubuntu "*) FAMILY="debian" ;;
                *" fedora "* | *" rhel "* | *" centos "*) FAMILY="fedora" ;;
                *" arch "*) FAMILY="arch" ;;
                *) FAMILY="linux" ;;
            esac
            ;;
        *)
            die "this script supports macOS, Linux and WSL. On Windows, use install.ps1 in PowerShell."
            ;;
    esac
}

# --- fixes, per platform ----------------------------------------------------

fix_php() {
    case "$FAMILY" in
        mac)    fix "/bin/bash -c \"\$(curl -fsSL https://php.new/install/mac/${PHP_SERIES})\"   or   brew install php" ;;
        debian) fix "/bin/bash -c \"\$(curl -fsSL https://php.new/install/linux/${PHP_SERIES})\"" ;
                fix "or: sudo add-apt-repository ppa:ondrej/php && sudo apt install php${PHP_SERIES}-cli" ;;
        fedora) fix "/bin/bash -c \"\$(curl -fsSL https://php.new/install/linux/${PHP_SERIES})\"   or   sudo dnf install php-cli" ;;
        arch)   fix "sudo pacman -S php" ;;
        *)      fix "/bin/bash -c \"\$(curl -fsSL https://php.new/install/linux/${PHP_SERIES})\"" ;;
    esac
}

# Package that provides a PHP extension on Debian and Ubuntu.
debian_extension_package() {
    case "$1" in
        pdo_mysql) printf 'php%s-mysql' "$PHP_SERIES" ;;
        dom | xml) printf 'php%s-xml' "$PHP_SERIES" ;;
        ctype | fileinfo | openssl | tokenizer) printf 'php%s-common' "$PHP_SERIES" ;;
        *) printf 'php%s-%s' "$PHP_SERIES" "$1" ;;
    esac
}

fix_extensions() {
    case "$FAMILY" in
        mac)
            fix "Homebrew's PHP includes all of them: brew reinstall php"
            ;;
        debian)
            packages=""
            for extension in "$@"; do
                package=$(debian_extension_package "$extension")
                case " $packages " in
                    *" $package "*) ;;
                    *) packages="$packages $package" ;;
                esac
            done
            fix "sudo apt install$packages"
            ;;
        fedora)
            packages=""
            for extension in "$@"; do
                case "$extension" in
                    pdo_mysql) packages="$packages php-mysqlnd" ;;
                    dom) packages="$packages php-xml" ;;
                    *) packages="$packages php-$extension" ;;
                esac
            done
            fix "sudo dnf install$packages"
            ;;
        arch)
            fix "enable them in /etc/php/php.ini (extension=...), and: sudo pacman -S php-intl"
            ;;
        *)
            fix "install the PHP ${PHP_SERIES} packages for: $*"
            ;;
    esac
}

fix_composer() {
    fix "https://getcomposer.org/download/"
    case "$FAMILY" in
        mac)    fix "or: brew install composer" ;;
        debian) fix "or: sudo apt install composer   (check that it is version 2)" ;;
        fedora) fix "or: sudo dnf install composer" ;;
        arch)   fix "or: sudo pacman -S composer" ;;
        *)      ;;
    esac
}

fix_node() {
    case "$FAMILY" in
        mac)    fix "brew install node@${NODE_MINIMUM}   or   https://nodejs.org" ;;
        debian) fix "curl -fsSL https://deb.nodesource.com/setup_${NODE_MINIMUM}.x | sudo -E bash - && sudo apt install nodejs" ;;
        fedora) fix "sudo dnf install nodejs   (or use nvm: nvm install ${NODE_MINIMUM})" ;;
        arch)   fix "sudo pacman -S nodejs npm" ;;
        *)      fix "https://nodejs.org   (or use nvm: nvm install ${NODE_MINIMUM})" ;;
    esac
}

fix_git() {
    case "$FAMILY" in
        mac)    fix "xcode-select --install   or   brew install git" ;;
        debian) fix "sudo apt install git" ;;
        fedora) fix "sudo dnf install git" ;;
        arch)   fix "sudo pacman -S git" ;;
        *)      fix "https://git-scm.com/downloads" ;;
    esac
}

fix_mysql_client() {
    case "$FAMILY" in
        mac)    fix "brew install mysql-client" ;;
        debian) fix "sudo apt install mysql-client" ;;
        fedora) fix "sudo dnf install mysql" ;;
        arch)   fix "sudo pacman -S mariadb-clients" ;;
        *)      fix "install the MySQL command-line client for your system" ;;
    esac
}

# --- preflight --------------------------------------------------------------

check_php() {
    if ! command -v php >/dev/null 2>&1; then
        bad "PHP is not installed (need ${PHP_SERIES} or newer)"
        fix_php
        NEEDS_PHP_NEW=1
        return
    fi

    version=$(php -r 'echo PHP_VERSION;' 2>/dev/null || echo "unknown")

    if ! php -r 'exit(PHP_VERSION_ID >= 80400 ? 0 : 1);' 2>/dev/null; then
        bad "PHP ${version} is too old (need ${PHP_SERIES} or newer)"
        fix_php
        NEEDS_PHP_NEW=1
        return
    fi

    ok "PHP ${version}"

    missing=""
    for extension in $EXTENSIONS; do
        if ! php -r "exit(extension_loaded('${extension}') ? 0 : 1);" 2>/dev/null; then
            missing="$missing $extension"
        fi
    done

    if [ -n "$missing" ]; then
        bad "PHP extensions missing:${missing}"
        # Word splitting is the point: one argument per extension.
        # shellcheck disable=SC2086
        fix_extensions $missing
    else
        ok "PHP extensions"
    fi
}

check_composer() {
    if ! command -v composer >/dev/null 2>&1; then
        bad "Composer is not installed (need version 2)"
        fix_composer
        NEEDS_PHP_NEW=1
        return
    fi

    major=$(composer --version --no-ansi 2>/dev/null | sed -n 's/^Composer version \([0-9][0-9]*\).*/\1/p' | head -n 1)

    if [ -z "$major" ] || [ "$major" -lt 2 ]; then
        bad "Composer ${major:-?} is too old (need version 2)"
        fix "composer self-update --2"
        return
    fi

    ok "Composer $(composer --version --no-ansi 2>/dev/null | sed -n 's/^Composer version \([0-9.]*\).*/\1/p' | head -n 1)"
}

check_node() {
    if ! command -v node >/dev/null 2>&1; then
        bad "Node.js is not installed (need ${NODE_MINIMUM} or newer)"
        fix_node
        return
    fi

    major=$(node -p 'process.versions.node.split(".")[0]' 2>/dev/null || echo 0)

    if [ "$major" -lt "$NODE_MINIMUM" ]; then
        bad "Node.js $(node -v 2>/dev/null) is too old (need ${NODE_MINIMUM} or newer)"
        fix_node
    else
        ok "Node.js $(node -v 2>/dev/null)"
    fi

    if command -v npm >/dev/null 2>&1; then
        ok "npm $(npm -v 2>/dev/null)"
    else
        bad "npm is not installed"
        fix_node
    fi
}

check_git() {
    if command -v git >/dev/null 2>&1; then
        ok "$(git --version)"
    else
        bad "git is not installed"
        fix_git
    fi
}

check_mysql_client() {
    if command -v mysqldump >/dev/null 2>&1 && command -v mysql >/dev/null 2>&1; then
        ok "mysql client"
    else
        # Not fatal: the application talks to MySQL through PHP. Only
        # backups need mysqldump.
        warn "mysql / mysqldump not found. The app will run, but backups cannot dump the database."
        fix_mysql_client
    fi
}

check_target() {
    if [ ! -e "$APP_DIR" ]; then
        ok "${APP_DIR} is free"
        return
    fi

    if [ -d "$APP_DIR" ] && [ -z "$(ls -A "$APP_DIR" 2>/dev/null)" ]; then
        ok "${APP_DIR} exists and is empty"
        return
    fi

    bad "${APP_DIR} already exists and is not empty"
    fix "choose another name, or move that directory away. This script will not delete it."
}

offer_php_new() {
    say ""
    say "PHP ${PHP_SERIES} and Composer can be installed together by the official php.new installer."

    if ! ask_yes_no "Run it now?"; then
        return
    fi

    if [ "$OS" = "mac" ]; then
        url="https://php.new/install/mac/${PHP_SERIES}"
    else
        url="https://php.new/install/linux/${PHP_SERIES}"
    fi

    say ""
    say "${DIM}\$ /bin/bash -c \"\$(curl -fsSL ${url})\"${RESET}"
    /bin/bash -c "$(curl -fsSL "$url")" </dev/tty

    say ""
    say "${BOLD}Restart your terminal, then run the GhostDash install command again.${RESET}"
    say ""
    exit 0
}

# --- version ----------------------------------------------------------------

describe_version() {
    if [ -n "${GHOSTDASH_SOURCE:-}" ]; then
        printf 'local checkout at %s' "$GHOSTDASH_SOURCE"
        return
    fi

    if [ -n "${GHOSTDASH_VERSION:-}" ]; then
        printf '%s' "$GHOSTDASH_VERSION"
        return
    fi

    # Informational only: Composer resolves the version itself. Packagist
    # lists releases newest first.
    latest=""
    if command -v curl >/dev/null 2>&1; then
        latest=$(curl -fsSL --max-time 8 "https://repo.packagist.org/p2/${PACKAGE}.json" 2>/dev/null \
            | tr ',' '\n' | sed -n 's/.*"version":"\(v\{0,1\}[0-9][0-9.]*\)".*/\1/p' | head -n 1) || latest=""
    fi

    if [ -n "$latest" ]; then
        printf '%s (latest stable)' "$latest"
    else
        printf 'latest stable'
    fi
}

# Whether Packagist lists the package. Only a definite "not found" counts:
# when Packagist cannot be reached, Composer is left to report that itself.
on_packagist() {
    command -v curl >/dev/null 2>&1 || return 0

    status=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "https://repo.packagist.org/p2/${PACKAGE}.json" 2>/dev/null) || status=""

    [ "$status" != "404" ]
}

# The newest release tag in the GitHub repository, such as v1.2.0.
latest_tag() {
    git ls-remote --tags --refs "${REPOSITORY}.git" 2>/dev/null \
        | sed 's#.*refs/tags/##' \
        | grep -E '^v?[0-9]+(\.[0-9]+)*$' \
        | awk -F. '{ v = $0; sub(/^v/, "", v); split(v, n, "."); printf "%010d%010d%010d %s\n", n[1], n[2], n[3], $0 }' \
        | sort | tail -n 1 | cut -d' ' -f2
}

# Describes one release of the GitHub repository as a Composer repository
# in a temporary file, and prints its path. GitHub's archive of a tag
# leaves out the same kit-only files as a Packagist download.
github_repository_file() {
    ref="${GHOSTDASH_VERSION:-}"

    if [ -z "$ref" ]; then
        ref=$(latest_tag) || ref=""
        [ -n "$ref" ] || die "Could not find a release of ${PACKAGE} on ${REPOSITORY}."
    fi

    case "$ref" in
        dev-*) version="$ref"; archive="${REPOSITORY}/archive/refs/heads/${ref#dev-}.zip" ;;
        *) version="$ref"; archive="${REPOSITORY}/archive/refs/tags/${ref}.zip" ;;
    esac

    directory=$(mktemp -d 2>/dev/null || mktemp -d -t ghostdash)
    printf '{"packages":{"%s":{"%s":{"name":"%s","version":"%s","type":"project","dist":{"type":"zip","url":"%s"}}}}}\n' \
        "$PACKAGE" "$version" "$PACKAGE" "$version" "$archive" > "${directory}/packages.json"

    GITHUB_VERSION="$version"
    GITHUB_REPOSITORY_FILE="${directory}/packages.json"
}

# --- main -------------------------------------------------------------------

main() {
    trap on_error ERR

    banner
    detect_platform

    # The first argument that is not an option is the project directory.
    # Everything else is for dash:install.
    forwarded=()
    for argument in "$@"; do
        if [ -z "$APP_DIR" ] && [ "${argument#-}" = "$argument" ]; then
            APP_DIR="$argument"
        else
            forwarded+=("$argument")
        fi
    done

    if [ -z "$APP_DIR" ] && has_tty; then
        printf 'Project name: ' >/dev/tty
        read -r APP_DIR </dev/tty || APP_DIR=""
    fi

    if [ -z "$APP_DIR" ]; then
        say "Usage: curl -fsSL https://raw.githubusercontent.com/${PACKAGE}/main/install.sh | bash -s -- my-app [options]"
        say ""
        say "Options are those of \`php artisan dash:install\`, for example:"
        say "  --db-username=root --create-db --admin-email=you@example.com --no-interaction"
        exit 1
    fi

    case "$APP_DIR" in
        *[!A-Za-z0-9._/-]*) die "'${APP_DIR}' is not a usable directory name. Use letters, digits, dots, dashes and underscores." ;;
    esac

    say "  Installing ${BOLD}${PACKAGE}${RESET}: $(describe_version)"
    say "  Into       ${BOLD}${APP_DIR}${RESET}"
    say ""
    say "${BOLD}Checking requirements${RESET}"

    check_php
    check_composer
    check_node
    check_git
    check_mysql_client
    check_target

    if [ "$PROBLEMS" -gt 0 ]; then
        if [ "$NEEDS_PHP_NEW" -eq 1 ]; then
            offer_php_new
        fi

        say ""
        say "${RED}${PROBLEMS} requirement(s) are not met.${RESET} Fix them with the commands shown above, then run this again."
        say "Nothing was installed or changed."
        exit 1
    fi

    say ""
    say "${BOLD}Creating the project${RESET}"

    command=(composer create-project "$PACKAGE" "$APP_DIR")

    if [ -n "${GHOSTDASH_SOURCE:-}" ]; then
        [ -f "${GHOSTDASH_SOURCE}/composer.json" ] || die "GHOSTDASH_SOURCE (${GHOSTDASH_SOURCE}) does not contain a composer.json."
        source_path=$(cd "$GHOSTDASH_SOURCE" && pwd)
        escaped=$(printf '%s' "$source_path" | sed 's/\\/\\\\/g; s/"/\\"/g')
        # A copy, not a symlink: the result must be a project of its own.
        command+=(--repository "{\"type\":\"path\",\"url\":\"${escaped}\",\"options\":{\"symlink\":false}}" --stability dev)
    elif ! on_packagist; then
        github_repository_file
        say "${DIM}Packagist does not list ${PACKAGE} yet, so ${GITHUB_VERSION} is installed from ${REPOSITORY}.${RESET}"
        command+=("$GITHUB_VERSION" --repository "$GITHUB_REPOSITORY_FILE")
        case "$GITHUB_VERSION" in
            dev-*) command+=(--stability dev) ;;
        esac
    elif [ -n "${GHOSTDASH_VERSION:-}" ]; then
        command+=("$GHOSTDASH_VERSION")
    fi

    command+=(--no-interaction)

    say "${DIM}\$ ${command[*]}${RESET}"

    # The project's own post-create hook would start the installer too. It
    # is started below instead, with the options given to this script.
    #
    # COMPOSER_MIRROR_PATH_REPOS makes Composer copy a path repository even
    # where it would prefer to symlink it. A symlinked "project" would be
    # the source checkout itself, and everything below would modify that.
    GHOSTDASH_SKIP_AUTO_INSTALL=1 COMPOSER_MIRROR_PATH_REPOS=1 "${command[@]}" </dev/null

    if [ -L "$APP_DIR" ]; then
        die "${APP_DIR} was created as a link to the source instead of a copy. Remove the link (rm ${APP_DIR}) and report this."
    fi

    PROJECT_CREATED=1
    cd "$APP_DIR"

    say ""
    say "${BOLD}Running the installer${RESET}"
    # This line ends up in terminal scrollback and CI logs, so a password
    # passed as an option is not repeated in it.
    shown=""
    for argument in ${forwarded[@]+"${forwarded[@]}"}; do
        case "$argument" in
            --*password*=*) argument="${argument%%=*}=********" ;;
        esac
        shown="${shown} ${argument}"
    done
    say "${DIM}\$ php artisan dash:install${shown}${RESET}"

    # From here on a failure belongs to dash:install, which explains itself.
    trap - ERR
    status=0

    # ${forwarded[@]+...} keeps bash 3.2 (macOS) from treating an empty
    # array as an unset variable under `set -u`.
    if has_tty; then
        php artisan dash:install ${forwarded[@]+"${forwarded[@]}"} </dev/tty || status=$?
    else
        php artisan dash:install ${forwarded[@]+"${forwarded[@]}"} </dev/null || status=$?
    fi

    if [ "$status" -ne 0 ]; then
        resume_hint >&2
        exit "$status"
    fi
}

# Nothing runs until the whole file has arrived: a download cut off half way
# must not execute half a script.
main "$@"

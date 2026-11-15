<?php

declare(strict_types=1);

use App\Support\Installer\EnvFile;
use Dotenv\Dotenv;

beforeEach(function (): void {
    $this->path = sys_get_temp_dir().'/ghostdash-env-'.uniqid();
});

afterEach(function (): void {
    if (is_file($this->path)) {
        unlink($this->path);
    }
});

it('replaces a value in place and leaves everything else alone', function (): void {
    file_put_contents($this->path, "# Application\nAPP_NAME=Laravel\n\n# Database\nDB_HOST=127.0.0.1\nDB_PORT=3306\n");

    new EnvFile($this->path)->set(['DB_HOST' => 'db.internal']);

    expect(file_get_contents($this->path))->toBe("# Application\nAPP_NAME=Laravel\n\n# Database\nDB_HOST=db.internal\nDB_PORT=3306\n");
});

it('appends a key that is not there yet', function (): void {
    file_put_contents($this->path, "APP_NAME=Laravel\n");

    new EnvFile($this->path)->set(['NEW_KEY' => 'value']);

    expect(file_get_contents($this->path))->toBe("APP_NAME=Laravel\nNEW_KEY=value\n");
});

it('does not confuse a key with one that merely starts the same', function (): void {
    file_put_contents($this->path, "DB_PASSWORD_HINT=keep\nDB_PASSWORD=old\n");

    $env = new EnvFile($this->path);
    $env->set(['DB_PASSWORD' => 'new']);

    expect($env->get('DB_PASSWORD_HINT'))->toBe('keep')
        ->and($env->get('DB_PASSWORD'))->toBe('new');
});

it('round-trips awkward values exactly', function (string $value): void {
    file_put_contents($this->path, "SECRET=\n");

    $env = new EnvFile($this->path);
    $env->set(['SECRET' => $value]);

    expect($env->get('SECRET'))->toBe($value)
        // And through a real dotenv parser, which is what the application uses.
        ->and(Dotenv::parse((string) file_get_contents($this->path))['SECRET'])->toBe($value);
})->with([
    'spaces' => ['My Application'],
    'a hash' => ['pa#ss'],
    'a dollar reference' => ['pa$1ss ${HOME}'],
    'double quotes' => ['say "hi"'],
    'a backslash' => ['back\\slash'],
    'single quotes' => ["it's"],
    'everything at once' => ['p@ss "w" $1 #x \\y'],
    'an equals sign' => ['a=b=='],
    'empty' => [''],
]);

it('writes a base64 key unquoted so key:generate can find it later', function (): void {
    file_put_contents($this->path, "APP_KEY=\n");
    $key = 'base64:'.base64_encode(random_bytes(32));

    new EnvFile($this->path)->set(['APP_KEY' => $key]);

    expect(file_get_contents($this->path))->toBe("APP_KEY={$key}\n");
});

it('reads values, ignoring quotes and inline comments', function (): void {
    file_put_contents($this->path, "A=plain\nB=\"quoted value\"\nC='single'\nD=value # comment\nE=\n");

    $env = new EnvFile($this->path);

    expect($env->get('A'))->toBe('plain')
        ->and($env->get('B'))->toBe('quoted value')
        ->and($env->get('C'))->toBe('single')
        ->and($env->get('D'))->toBe('value')
        ->and($env->get('E'))->toBe('')
        ->and($env->has('E'))->toBeFalse()
        ->and($env->get('MISSING', 'fallback'))->toBe('fallback');
});

it('writes booleans and integers as dotenv expects', function (): void {
    $env = new EnvFile($this->path);
    $env->set(['FLAG' => true, 'OFF' => false, 'PORT' => 3306]);

    expect(file_get_contents($this->path))->toBe("\nFLAG=true\nOFF=false\nPORT=3306\n");
});

it('creates itself from a template', function (): void {
    $template = $this->path.'.example';
    file_put_contents($template, "APP_NAME=Template\n");

    $env = new EnvFile($this->path);

    expect($env->exists())->toBeFalse();

    $env->createFrom($template);

    expect($env->get('APP_NAME'))->toBe('Template');

    unlink($template);
});

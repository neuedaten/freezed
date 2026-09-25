# CLI reference

Freezed ships a single command: `freezed`. After a Composer install it is
available at `./vendor/bin/freezed`.

```bash
./vendor/bin/freezed <command> [options]
```

## Commands

| Command | Aliases | Description |
|---------|---------|-------------|
| `build` | `compile` | Render the site into `public/`. **Default** when no command is given. |
| `install` | `init` | Scaffold the project: create folders and copy the default theme, example content and config. |
| `serve` | | Serve `public/` with PHP's built-in web server. |
| `watch` | | Rebuild automatically when source files change. |
| `run` | | Build, serve and watch together (development mode). |
| `cache:flush` | | Remove cached processed images (`freezed:image`). |
| `help` | `-h`, `--help` | Show usage information, package commands included. |
| `version` | `-V`, `--version` | Print the Freezed version. |
| `<name>` | | A command registered by a package or the project, see [Package commands](#package-commands). |

An unknown command fails with a message; `freezed` without a command builds.

### `freezed build`

```bash
./vendor/bin/freezed build
# or simply:
./vendor/bin/freezed
```

Renders all content through the active themes and writes static files to
`public/`. Requires a `freezed.config.php` in the project root; if none is found
it tells you to run `install` first.

On success it prints a one-line summary:

```text
Built 3 pages (3 files, 3 resources, sitemap) in 32 ms
```

`sitemap` appears when [`sitemap.enabled`](configuration.md#sitemap) is set and
`public/sitemap.xml` was written. Warnings (e.g. dead `CONTENT:` links or a
missing `siteUrl`) are printed above the summary; they don't fail the build.

#### Build options

Pass build options as `--<key>:<value>` (or `--<key>=<value>`). Every option is
collected into a build configuration that is exposed to your templates through
the `build` variable:

```bash
./vendor/bin/freezed build --enviroment:development
```

```html
<f:if condition="{build.enviroment} == 'development'">
    <!-- only rendered for development builds -->
</f:if>
```

Options can be combined with the command and with each other:

```bash
./vendor/bin/freezed build --enviroment:staging --debug
```

A flag without a value (e.g. `--debug`) is exposed as `true`. Options not passed
on the command line are simply absent from `build`, so the condition above
evaluates to false for a default build.

### `freezed install`

```bash
./vendor/bin/freezed install
```

Creates `content/`, `themes/`, `static/` and `public/` if missing, and copies the
default theme, example content and a starter `freezed.config.php` **only when the
targets are empty**. It is safe to run on an existing project — it will not
overwrite your files.

### `freezed cache:flush`

```bash
./vendor/bin/freezed cache:flush
```

Deletes all cached processed images from `imageCacheDirectory`
(`var/cache/images` by default).

You don't need it to pick up changes: every parameter that affects a processed
image — including the source content and `quality` — is part of the generated
filename, so a change always produces a new file. Use it to clear out the
superseded files that accumulate there, for instance after upgrading Freezed or
after a round of image tweaking.

### `freezed run`

Builds once, serves `public/` in the background and watches for changes in
the foreground. A flag named like a registered command starts that command
as a second background process, stopped together with the server:

```bash
./vendor/bin/freezed run --desk        # site on :8080, Desk UI on :8081
```

## Package commands

A package adds commands to `freezed` by declaring them in its
`composer.json`:

```json
"extra": {
    "freezed": {
        "commands": {
            "desk": "Neuedaten\\FreezedDesk\\Commands\\ServeCommand",
            "desk:show": "Neuedaten\\FreezedDesk\\Commands\\ShowCommand"
        }
    }
}
```

A project adds or overrides commands with the [`commands`](configuration.md#commands)
key of `freezed.config.php`. Each class implements
`Neuedaten\Freezed\Commands\CommandInterface`:

```php
public function execute(array $args, array $options): int;
```

`$args` holds the positional arguments after the command name, `$options`
the parsed `--key:value` options. The command runs after the project
configuration has been loaded into the `ConfigService` and logging has been
configured, but before the core validates the project's directories: it
calls `ProjectPathsService::getInstance()->validate()` itself, once it has
prepared whatever the project needs (a folder it declares, for instance).
Names are lowercase (`desk`, `desk:show`) and cannot take a built-in name.

## Project root detection

The command auto-detects the project root by:

1. honouring the `FREEZED_ROOT` environment variable, if set;
2. otherwise searching upward from the current directory for `freezed.config.php`;
3. otherwise falling back to the current working directory.

A `freezed.config.php` found *above* the current directory is only used when
it belongs to the user running the command. The file is executable PHP, and on
a shared machine anyone could place one in `/tmp` or another common parent
directory. If the owner differs, the command stops with a hint; run it inside
the project or set `FREEZED_ROOT` to use that file anyway. (Without the POSIX
extension, and on Windows, the owner cannot be checked and the file is used.)

Run a build against a specific project from anywhere:

```bash
FREEZED_ROOT=/path/to/site ./vendor/bin/freezed build
```

## Composer scripts (skeleton)

The skeleton's `composer.json` exposes convenience scripts:

```bash
composer run build          # ./vendor/bin/freezed build
composer run install-site   # ./vendor/bin/freezed install
```

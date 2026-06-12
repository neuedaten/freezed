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
| `help` | `-h`, `--help` | Show usage information. |
| `version` | `-V`, `--version` | Print the Freezed version. |

### `freezed build`

```bash
./vendor/bin/freezed build
# or simply:
./vendor/bin/freezed
```

Renders all content through the active themes and writes static files to
`public/`. Requires a `freezed.config.php` in the project root; if none is found
it tells you to run `install` first.

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

## Project root detection

The command auto-detects the project root by:

1. honouring the `FREEZED_ROOT` environment variable, if set;
2. otherwise searching upward from the current directory for `freezed.config.php`;
3. otherwise falling back to the current working directory.

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

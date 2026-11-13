#!/usr/bin/env bash
#
# Deploy the latest code on a production server (no Docker).
#
# Follows "Updating the server later" in docs/production/deployment.md:
# maintenance mode, pull, install, build, migrate, cache, restart workers, bring the site back up.
#
# It starts by showing the branch this server is on now and the branches on the remote that it can
# update from (newest first, with how many new commits each one has). On a terminal it then asks
# which one to deploy; press Enter to take the default.
#
# Usage:
#   ./deploy.sh                       list the branches, ask which one (Enter = DEPLOY_BRANCH, else the branch now checked out)
#   ./deploy.sh -b main               deploy that branch, no question
#   ./deploy.sh --list                only show the current and available branches, then stop
#   ./deploy.sh --skip-build          skip npm ci and the asset build (no frontend change)
#   ./deploy.sh --dry-run             print every step without running anything
#   ./deploy.sh -y                    do not ask anything: deploy the default (or -b) branch
#
# Optional environment:
#   DEPLOY_BRANCH      branch to deploy (default: the branch now checked out)
#   DEPLOY_REMOTE      git remote (default origin)
#   DEPLOY_BRANCH_LIMIT  how many remote branches to list (default 15)
#   DEPLOY_RELOAD_CMD  command that reloads PHP-FPM, for example "sudo systemctl reload php8.5-fpm"
#
# If a step fails the site is brought back up when nothing risky has run yet. Once migrations
# have started it stays in maintenance mode, because the code and the database may disagree.

set -Eeuo pipefail

# Default to the branch this server is already on, so Enter never switches a server to another client.
CURRENT_DEFAULT=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || true)
[ "$CURRENT_DEFAULT" = "HEAD" ] && CURRENT_DEFAULT=""
BRANCH="${DEPLOY_BRANCH:-${CURRENT_DEFAULT:-main}}"
REMOTE="${DEPLOY_REMOTE:-origin}"
BRANCH_LIMIT="${DEPLOY_BRANCH_LIMIT:-15}"
BRANCH_GIVEN=false
LIST_ONLY=false
SKIP_BUILD=false
DRY_RUN=false
ASSUME_YES=false

usage() {
    sed -n '3,/^set -E/p' "$0" | sed '$d' | sed 's/^# \{0,1\}//'
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        -b|--branch) BRANCH="${2:?--branch needs a name}"; BRANCH_GIVEN=true; shift 2 ;;
        --list) LIST_ONLY=true; shift ;;
        --skip-build) SKIP_BUILD=true; shift ;;
        --dry-run) DRY_RUN=true; shift ;;
        -y|--yes) ASSUME_YES=true; shift ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown option: $1" >&2; usage >&2; exit 2 ;;
    esac
done

cd "$(dirname "${BASH_SOURCE[0]}")"

if [ -t 1 ]; then
    BOLD=$'\033[1m'; GREEN=$'\033[32m'; YELLOW=$'\033[33m'; RED=$'\033[31m'; RESET=$'\033[0m'
else
    BOLD=''; GREEN=''; YELLOW=''; RED=''; RESET=''
fi

step() { printf '\n%s==> %s%s\n' "$BOLD" "$1" "$RESET"; }
ok() { printf '%s%s%s\n' "$GREEN" "$1" "$RESET"; }
warn() { printf '%s%s%s\n' "$YELLOW" "$1" "$RESET"; }
fail() { printf '%s%s%s\n' "$RED" "$1" "$RESET" >&2; exit 1; }

# Print the command, and run it unless this is a dry run.
run() {
    printf '  $ %s\n' "$*"

    if [ "$DRY_RUN" = false ]; then
        "$@"
    fi
}

SITE_DOWN=false
PAST_POINT_OF_NO_RETURN=false
PREVIOUS_COMMIT=""
STARTED_AT=$(date +%s)

on_exit() {
    local code=$?

    if [ "$code" -eq 0 ] || [ "$DRY_RUN" = true ]; then
        return
    fi

    printf '\n%sDeployment failed (exit %s).%s\n' "$RED" "$code" "$RESET" >&2

    if [ "$SITE_DOWN" = true ] && [ "$PAST_POINT_OF_NO_RETURN" = false ]; then
        warn "Bringing the site back up, nothing irreversible had run."
        php artisan up || true
    elif [ "$SITE_DOWN" = true ]; then
        warn "The site is still in maintenance mode: migrations had started, so check the database first."
        warn "When it is safe, bring it back with: php artisan up"
    fi

    if [ -n "$PREVIOUS_COMMIT" ]; then
        warn "Previous commit was $PREVIOUS_COMMIT. To go back to the old code: git reset --hard $PREVIOUS_COMMIT"
        warn "then rerun composer install and the asset build. Migrations are not rolled back."
    fi
}
trap on_exit EXIT

# ---------------------------------------------------------------- branches

# Show where this server is now and which remote branches it can update from, then (on a terminal,
# when no -b was given) ask which one to deploy. Fetching branch names changes nothing on disk
# except the remote-tracking refs, so it is also done on a dry run.
show_branches() {
    local current commit when subject count i choice name hash age line ahead behind note marker
    local -a names=() hashes=() ages=() notes=()

    step "Branches"

    current=$(git rev-parse --abbrev-ref HEAD)
    commit=$(git rev-parse --short HEAD)
    when=$(git log -1 --format='%cr' HEAD)
    subject=$(git log -1 --format='%s' HEAD)

    if [ "$current" = "HEAD" ]; then
        printf '  Current branch:  %s(detached HEAD)%s  %s, %s\n' "$YELLOW" "$RESET" "$commit" "$when"
    else
        printf '  Current branch:  %s%s%s  %s, %s\n' "$BOLD" "$current" "$RESET" "$commit" "$when"
    fi
    printf '                   %s\n' "$subject"

    if ! git fetch --prune --quiet "$REMOTE" 2>/dev/null; then
        warn "  Could not reach $REMOTE, so the list below may be out of date."
    fi

    while IFS='|' read -r name hash age; do
        [ -n "$name" ] || continue
        name="${name#"$REMOTE"/}"
        # origin/HEAD shows up as just "origin" in short form.
        [ "$name" = "HEAD" ] || [ "$name" = "$REMOTE" ] && continue

        behind=$(git rev-list --count "HEAD..$REMOTE/$name" 2>/dev/null || echo 0)
        ahead=$(git rev-list --count "$REMOTE/$name..HEAD" 2>/dev/null || echo 0)

        if [ "$behind" -eq 0 ] && [ "$ahead" -eq 0 ]; then
            note="same code as now"
        elif [ "$ahead" -eq 0 ]; then
            note="$behind new commit(s) to deploy"
        elif [ "$behind" -eq 0 ]; then
            note="$ahead commit(s) behind what is running now"
        else
            note="diverged: $behind new, $ahead not in it"
        fi

        names+=("$name"); hashes+=("$hash"); ages+=("$age"); notes+=("$note")
    done < <(git for-each-ref --sort=-committerdate --count="$((BRANCH_LIMIT + 1))" --format='%(refname:short)|%(objectname:short)|%(committerdate:relative)' "refs/remotes/$REMOTE" 2>/dev/null)

    printf '\n  Available on %s (newest first, up to %s).  > running now   * default\n' "$REMOTE" "$BRANCH_LIMIT"

    if [ "${#names[@]}" -eq 0 ]; then
        warn "  No remote branches found."
    else
        for i in "${!names[@]}"; do
            [ "$i" -ge "$BRANCH_LIMIT" ] && break
            marker=' '
            [ "${names[$i]}" = "$BRANCH" ] && marker='*'
            [ "${names[$i]}" = "$current" ] && marker='>'
            printf '   %2d) %s %-34s %s  %-15s %s\n' "$((i + 1))" "$marker" "${names[$i]}" "${hashes[$i]}" "${ages[$i]}" "${notes[$i]}"
        done
    fi

    if [ "$LIST_ONLY" = true ]; then
        trap - EXIT
        exit 0
    fi

    if [ "$BRANCH_GIVEN" = true ] || [ "$ASSUME_YES" = true ] || [ ! -t 0 ]; then
        printf '\n  Deploying:       %s\n' "$BRANCH"
        return
    fi

    printf '\n'
    read -r -p "Deploy which branch? Number or name [Enter = $BRANCH]: " choice

    if [ -z "$choice" ]; then
        return
    fi

    if [[ "$choice" =~ ^[0-9]+$ ]]; then
        if [ "$choice" -lt 1 ] || [ "$choice" -gt "${#names[@]}" ] || [ "$choice" -gt "$BRANCH_LIMIT" ]; then
            fail "No branch number $choice in the list."
        fi

        BRANCH="${names[$((choice - 1))]}"
    else
        BRANCH="$choice"
    fi
}

command -v git >/dev/null 2>&1 || fail "git is not installed or not on PATH."
git rev-parse --is-inside-work-tree >/dev/null 2>&1 || fail "This folder is not a git checkout."

show_branches

# ---------------------------------------------------------------- preflight

step "Checking the server"

for command in git php composer; do
    command -v "$command" >/dev/null 2>&1 || fail "$command is not installed or not on PATH."
done

if [ "$SKIP_BUILD" = false ]; then
    command -v npm >/dev/null 2>&1 || fail "npm is not installed. Install Node.js, or use --skip-build if no frontend file changed."
fi

[ -f artisan ] || fail "Run this from the project root (artisan not found)."
[ -f .env ] || fail ".env is missing. Follow docs/production/deployment.md, section First install."

APP_ENV_VALUE=$(grep -E '^APP_ENV=' .env | tail -n 1 | cut -d= -f2- | tr -d '"' || true)
APP_DEBUG_VALUE=$(grep -E '^APP_DEBUG=' .env | tail -n 1 | cut -d= -f2- | tr -d '"' || true)

if [ "$APP_ENV_VALUE" != "production" ]; then
    warn "APP_ENV is '${APP_ENV_VALUE:-unset}', not production."
fi

if [ "$APP_DEBUG_VALUE" = "true" ]; then
    warn "APP_DEBUG is true. Set it to false on a live server."
fi

dirty_files() { git status --porcelain --untracked-files=no; }

# npm install (instead of npm ci) rewrites the lockfile. It is generated and the repository copy is
# the source of truth, so restore it instead of stopping.
LOCKFILE_ONLY=false

if [ "$(dirty_files)" = " M package-lock.json" ]; then
    warn "package-lock.json was changed on this server (usually by npm install). Restoring the committed version."
    run git checkout -- package-lock.json
    LOCKFILE_ONLY=true
fi

if [ "$LOCKFILE_ONLY" = false ] && [ -n "$(dirty_files)" ]; then
    dirty_files >&2

    if [ "$DRY_RUN" = true ]; then
        warn "There are local changes to tracked files. A real deploy would stop here."
    else
        fail "There are local changes to tracked files. Commit, stash or discard them first, so the pull cannot clash."
    fi
fi

CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
PREVIOUS_COMMIT=$(git rev-parse --short HEAD)

printf '  Branch now:    %s (%s)\n' "$CURRENT_BRANCH" "$PREVIOUS_COMMIT"
printf '  Deploying:     %s/%s\n' "$REMOTE" "$BRANCH"
[ "$DRY_RUN" = true ] && warn "  Dry run: nothing below will actually run."

if [ "$ASSUME_YES" = false ] && [ "$DRY_RUN" = false ]; then
    if [ ! -t 0 ]; then
        fail "No terminal to ask for confirmation. Rerun with -y."
    fi

    read -r -p "Continue? [y/N] " answer

    case "$answer" in
        y|Y|yes|YES) ;;
        *) echo "Cancelled."; trap - EXIT; exit 1 ;;
    esac
fi

# ---------------------------------------------------------------- fetch first, while the site is still up

step "Fetching the latest code"
run git fetch "$REMOTE" "$BRANCH"

if [ "$DRY_RUN" = false ]; then
    git rev-parse --verify --quiet "$REMOTE/$BRANCH" >/dev/null || fail "Branch $REMOTE/$BRANCH does not exist."

    if [ "$(git rev-parse HEAD)" = "$(git rev-parse "$REMOTE/$BRANCH")" ] && [ "$CURRENT_BRANCH" = "$BRANCH" ]; then
        warn "Already on the latest commit. Continuing anyway: dependencies, migrations and caches are refreshed."
    fi
fi

# ---------------------------------------------------------------- deploy

step "Entering maintenance mode"
run php artisan down --retry=60 --refresh=15
SITE_DOWN=true

step "Updating the code"
run git checkout "$BRANCH"
run git pull --ff-only "$REMOTE" "$BRANCH"

step "Installing PHP dependencies"
run composer install --no-dev --optimize-autoloader --no-interaction --no-progress

if [ "$SKIP_BUILD" = true ]; then
    step "Skipping the asset build (--skip-build)"
else
    step "Building the frontend"
    run npm ci --no-audit --no-fund
    run npm run build
fi

step "Migrating the database"
PAST_POINT_OF_NO_RETURN=true
run php artisan migrate --force

step "Linking public storage"
if [ -L public/storage ]; then
    echo "  public/storage already linked."
else
    run php artisan storage:link
fi

step "Refreshing caches"
run php artisan optimize:clear
run php artisan optimize

if [ -n "${DEPLOY_RELOAD_CMD:-}" ]; then
    step "Reloading PHP-FPM"
    printf '  $ %s\n' "$DEPLOY_RELOAD_CMD"
    [ "$DRY_RUN" = false ] && bash -c "$DEPLOY_RELOAD_CMD"
fi

step "Restarting workers"
run php artisan queue:restart
run php artisan reverb:restart

step "Leaving maintenance mode"
run php artisan up
SITE_DOWN=false

# ---------------------------------------------------------------- done

NEW_COMMIT=$(git rev-parse --short HEAD)
ELAPSED=$(( $(date +%s) - STARTED_AT ))

printf '\n'

if [ "$DRY_RUN" = true ]; then
    ok "Dry run finished. Nothing was changed."
else
    ok "Deployed $BRANCH: $PREVIOUS_COMMIT -> $NEW_COMMIT in ${ELAPSED}s."
fi

warn "The scheduler (schedule:work) is not restarted here. If it runs under a supervisor, restart it so it loads the new code."

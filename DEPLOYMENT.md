# Production deployment

Production deployments are performed by [`.github/workflows/deploy-production.yml`](.github/workflows/deploy-production.yml).
The workflow runs **only** after a push to `main`—normally the merge of a pull request from `develop` into `main`. It does not run for pull requests, `develop`, or other branches.

The workflow connects to Hetzner by SSH. The server then fast-forwards its own
checkout of `origin/main`; no project files are copied from the GitHub runner.
Production already uses this Git checkout successfully.

## Normal flow

1. Create a feature branch from the current integration branch.
2. Merge reviewed work into `develop`.
3. Open and merge a reviewed pull request from `develop` to `main`.
4. The push to `main` starts the GitHub `Deploy production` action.
5. The action connects to Hetzner and validates the production checkout.
6. Hetzner fast-forwards to `origin/main`, installs the locked dependencies,
   runs `php craft up --interactive=0`, and clears Craft caches.

A pull request by itself does not deploy. There is no Jenkins job and no
server-side cron involved.

## GitHub configuration

1. In GitHub, create an Environment called `production`. Add required reviewers there if deployments should need approval after each merge.
2. Add these **environment secrets** to `production`:

   | Secret | Value |
   | --- | --- |
   | `HETZNER_HOST` | Hetzner SSH hostname or IP address |
   | `HETZNER_USER` | Dedicated deployment SSH user |
   | `HETZNER_SSH_PRIVATE_KEY` | Private key for that deployment user |
   | `DEPLOY_KNOWN_HOSTS` | Pinned `known_hosts` entry for the Hetzner SSH host |
   | `DEPLOY_PATH` | Absolute path of the production **Git checkout** |

   Generate `DEPLOY_KNOWN_HOSTS` from a separately verified host fingerprint. The pinned host-key secret is retained because this project deploys application code, not only a static release.

3. Ensure `DEPLOY_PATH` is a clean checkout on branch `main` with `origin`
   pointing to GitHub, and that its deploy user can read the repository and
   write `vendor/`, `storage/`, `web/cpresources/`, and other Craft runtime
   directories. The deploy user needs `php`, `composer`, and the application
   `.env` on the server.
4. Grant the deploy key read-only access to the GitHub repository. It only needs access for `git fetch` on Hetzner.

## Safety behaviour

The workflow refuses to deploy if the target is not a Git checkout on `main`, if it contains uncommitted changes, or if it has diverged from `origin/main`. It uses `git merge --ff-only`, so it will not overwrite server-side changes or create a merge commit.

After the fast-forward it installs the exact locked Composer dependencies,
runs Craft's non-interactive upgrade command, and clears Craft caches. A
failed command marks the deployment as failed; it does not attempt an
automatic rollback.

The workflow deliberately does not run `git clean`. Runtime and user-managed
paths must survive a code deployment, especially:

- `.env`;
- `storage/`;
- `vendor/` between the fast-forward and Composer install;
- `web/bilder/`;
- `web/media-assets/`;
- `web/imager/`; and
- `web/cpresources/`.

Do not put secrets or uploaded assets into Git to make a deployment work.

## Server repository

The active production directory is a normal Git working checkout on `main`.
Its `origin` remote points to GitHub and is the only repository path used by
the production workflow.

An older bare Hetzner repository may still appear as a local remote named
`production`. It is legacy infrastructure and is not part of the current
deployment path. New deployments, rollback procedures, and documentation must
not depend on it; it can be retired separately after confirming no external
automation still references it.

## Verification and recovery

After a deployment, verify at minimum:

- the homepage;
- one story detail page;
- the Craft control-panel login;
- `/api/v1/stories.json`;
- `/api/v1/story-reading.schema.json`; and
- an unsigned reading request, which should return `402` when payments are
  correctly enabled.

GitHub Actions reports a failed command, but the workflow has no automatic
application or database rollback. Recovery is a deliberate operator action:
inspect the failed step, restore the known-good code/database state when
needed, and re-run the same smoke checks before reopening traffic.

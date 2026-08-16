# Production deployment

Production deployments are performed by [`.github/workflows/deploy-production.yml`](.github/workflows/deploy-production.yml).
The workflow runs **only** after a push to `main`—normally the merge of a pull request from `develop` into `main`. It does not run for pull requests, `develop`, or other branches.

The workflow connects to Hetzner by SSH. The server then fast-forwards its own checkout of `origin/main`; no project files are copied from the GitHub runner. Its secret naming follows the existing `dguv-kompendium` convention where applicable.

## One-time setup

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

3. Ensure `DEPLOY_PATH` is a clean checkout on branch `main` with `origin` pointing to GitHub, and that its deploy user can read the repository and write `vendor/`, `storage/`, `web/cpresources/`, and other Craft runtime directories. The deploy user needs `php`, `composer`, and the application `.env` on the server.
4. Grant the deploy key read-only access to the GitHub repository. It only needs access for `git fetch` on Hetzner.

## Safety behaviour

The workflow refuses to deploy if the target is not a Git checkout on `main`, if it contains uncommitted changes, or if it has diverged from `origin/main`. It uses `git merge --ff-only`, so it will not overwrite server-side changes or create a merge commit.

After the fast-forward it installs the exact locked Composer dependencies, runs Craft's non-interactive upgrade command, and clears Craft caches. A failed command marks the deployment as failed; it does not attempt an automatic rollback.

## Existing server installation

The current Hetzner application path is not yet a Git worktree. Do the initial conversion separately, with a backup and a short maintenance window: create a clean `main` checkout, preserve the existing `.env` and user-generated files, verify it manually, then set `DEPLOY_PATH` to that checkout. Until this prerequisite is complete, the workflow intentionally exits before changing any server file.

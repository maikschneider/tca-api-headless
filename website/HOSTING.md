# Publishing the website

Nothing in this repository publishes the site. The `Website` workflow builds and tests it, then uploads `website-dist` (the `/tca-api-headless/` build) as a workflow artifact. Pick one of the options below; each one needs a decision and a settings change by a repository admin.

The build output is plain static files: `index.html` per route, `404.html`, `.nojekyll`, `sitemap.xml`, assets under `_astro/`, downloads under `files/`, recorded JSON under `proofs/` and the OpenAPI documents under `contracts/`. No server code runs. Two variables decide where it works:

| Variable | Project page (default) | Custom domain |
| --- | --- | --- |
| `WEBSITE_SITE` | `https://maikschneider.github.io` | `https://<domain>` |
| `WEBSITE_BASE` | `/tca-api-headless/` | `/` |

`WEBSITE_SITE` makes canonical, hreflang, Open Graph and sitemap URLs absolute; a build for one origin must not be served from another.

## Before any option

- [ ] Replace the placeholder text of Imprint and Privacy (pages 2008 and 2018, both languages) in the TYPO3 content, then `ddev website-export` and commit `website/typo3/`.
- [ ] Decide the origin: `maikschneider.github.io/tca-api-headless/` or a custom domain.
- [ ] Decide when to deploy: every push to `main` (below) or manual runs only (`workflow_dispatch`).

## Option A: GitHub Pages, project page

URL: `https://maikschneider.github.io/tca-api-headless/`. There is no `maikschneider.github.io` user site, so nothing redirects the project page to another domain.

1. **Settings → Pages → Build and deployment → Source: GitHub Actions.** This creates the `github-pages` environment; no branch is needed.
2. Add a deploy job to `.github/workflows/website.yml`. It reuses the tested, extracted project build:

    ```yaml
    # in the existing website job, after "Check the Composer dist exclusion":
          - uses: actions/upload-pages-artifact@v5
            if: github.event_name != 'pull_request'
            with:
              path: var/website/project

      # a second job, next to `website:`
      deploy:
        if: github.ref == 'refs/heads/main' && github.event_name != 'pull_request'
        needs: website
        runs-on: ubuntu-24.04
        permissions:
          pages: write
          id-token: write
        environment:
          name: github-pages
          url: ${{ steps.deployment.outputs.page_url }}
        steps:
          - id: deployment
            uses: actions/deploy-pages@v5
    ```

    The workflow keeps `permissions: contents: read` at the top; the job-level permissions above are the only extra rights.
3. Merge, then check the run's `deploy` job and open the URL. `/tca-api-headless/nope/` must answer with the site's 404 page.
4. Optional: **Settings → Environments → github-pages → Deployment branches: `main` only.**

Rollback: re-run the deploy job of an older successful run, or set the Pages source to *None* to take the site down.

## Option B: GitHub Pages with a custom domain

1. Verify the domain first: **profile Settings → Pages → Add a domain**, add the TXT record it shows, wait for *Verified*. This prevents takeover of the domain by other repositories.
2. Do steps 1–2 of option A, but upload the root build with the domain as origin. In the "Build both deployment bases" step:

    ```bash
    WEBSITE_SITE=https://<domain> WEBSITE_BASE=/ bun run build
    ```

    and upload `var/website/root` instead of `var/website/project`. The smoke tests already cover the `/` base.
3. **Settings → Pages → Custom domain: `<domain>` → Save.** With an Actions deployment no `CNAME` file is needed (an existing one is ignored).
4. DNS at the registrar, after step 3:
    - Subdomain (`www.<domain>`, `tca-api-headless.<domain>`): `CNAME` to `maikschneider.github.io`.
    - Apex (`<domain>`): `A` records `185.199.108.153`, `185.199.109.153`, `185.199.110.153`, `185.199.111.153`; optionally `AAAA` `2606:50c0:8000::153` to `2606:50c0:8003::153`. Or an `ALIAS`/`ANAME` to `maikschneider.github.io`. No wildcard records.
5. Check with `dig <domain> +noall +answer`, wait for the certificate, then tick **Enforce HTTPS**.

Removing the custom domain later: remove it under Settings → Pages *before* deleting the DNS records, never the other way round.

## Option C: another static host

Any host that serves files and a custom 404 works. Build with that host's origin and base, then upload the directory:

| Host | 404 | Deploy the built directory |
| --- | --- | --- |
| Cloudflare Pages | uses `404.html` automatically | `bunx wrangler pages deploy var/website/root --project-name tca-api-headless` (secret `CLOUDFLARE_API_TOKEN`) |
| Netlify | uses `404.html` automatically | `bunx netlify-cli deploy --dir var/website/root --prod` (secret `NETLIFY_AUTH_TOKEN`, `NETLIFY_SITE_ID`) |
| Own web server | `ErrorDocument 404 /404.html` (Apache) or `error_page 404 /404.html;` (nginx) | `rsync -a --delete var/website/root/ <user>@<host>:<docroot>/` |

These need an account, a token as repository secret and an extra workflow step; the TYPO3 build itself stays in the `Website` job. Serve text files gzip- or brotli-compressed: the Lighthouse budget assumes it (see `scripts/serve.ts`).

## What stays the same for every option

- The site is rebuilt from the TYPO3 fixture in CI, never from a local build.
- `website/` stays out of the extension package (`.gitattributes`, `packaging_exclude.php`).
- No tag, release or version bump is involved in publishing the site.

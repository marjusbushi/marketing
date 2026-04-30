# Cloudflare R2 setup — bucket `flare-social`

Cloudflare R2 holds the photos and videos that Flare schedules to Facebook and
Instagram. Meta's Graph API needs to fetch each upload from a public URL, so
the bucket has to be publicly readable through a custom domain. We use R2 over
S3 because R2's egress is free — every time Meta pulls down a 200 MB Reel, it
costs us nothing.

This bucket is separate from `flare-web` (the website's R2). They share an
account but use different access keys and a different public domain.

## What you (the human) do in the Cloudflare dashboard

### 1. Create the bucket

1. Open Cloudflare dashboard → **R2 Object Storage** → **Create bucket**.
2. Name: `flare-social`.
3. Location hint: leave automatic (R2 places it for you).
4. Default storage class: **Standard**.
5. Click **Create bucket**.

### 2. Create the API token

1. R2 → **Manage R2 API Tokens** → **Create API token**.
2. Permissions: **Object Read & Write**.
3. Specify bucket: **Apply to specific buckets only** → `flare-social`.
4. TTL: leave forever (or set 90 days and rotate; up to you).
5. Click **Create API Token**.
6. Copy the **Access Key ID** and **Secret Access Key** *immediately* — the
   secret is only shown once. Paste them into your `.env`:

   ```
   R2_ACCESS_KEY_ID=...
   R2_SECRET_ACCESS_KEY=...
   ```

7. Also copy the **endpoint** shown on the same page. It looks like
   `https://<account-id>.r2.cloudflarestorage.com`. Paste into:

   ```
   R2_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
   ```

### 3. Connect a custom domain (so Meta can fetch the media)

R2 buckets are private by default; we need a public CDN domain.

1. R2 → click the `flare-social` bucket → **Settings** → **Custom Domains** →
   **Connect Domain**.
2. Enter a domain you control, e.g. `media-flare.zeroabsolute.com` (or
   `flare-media.zeroabsolute.dev`, your call).
3. Cloudflare will add a CNAME record automatically if the domain is on
   Cloudflare DNS. Wait for the certificate to provision (~1–5 min).
4. Once it's green, copy the URL into:

   ```
   R2_PUBLIC_URL=https://media-flare.zeroabsolute.com
   ```

### 4. Flip the app onto R2

In your `.env`, set:

```
CONTENT_PLANNER_MEDIA_DISK=r2_social
```

That's the only switch — the Laravel disk `r2_social` (already configured in
`config/filesystems.php`) reads the four `R2_*` vars above.

## What the app does

| Concern | Handling |
| --- | --- |
| Disk config | `r2_social` in `config/filesystems.php` (region `auto`, `use_path_style_endpoint=true`, public visibility). |
| Default disk for Content Planner uploads | `CONTENT_PLANNER_MEDIA_DISK` env var, read by `ContentMediaService::__construct`. |
| Bucket name | `R2_BUCKET` env, defaults to `flare-social`. |
| Public URLs | `Storage::disk('r2_social')->url($path)` returns `R2_PUBLIC_URL/$path`. |

## Verify

After setting the four vars and `CONTENT_PLANNER_MEDIA_DISK=r2_social`:

```bash
php artisan tinker
>>> Storage::disk('r2_social')->put('verify.txt', 'hello from flare');
>>> echo Storage::disk('r2_social')->url('verify.txt');
```

Open the printed URL in a browser. You should see `hello from flare`. If you
get 403 / 404, the custom domain isn't wired through to the bucket — check
**R2 → bucket → Settings → Custom Domains** is "Active".

```bash
>>> Storage::disk('r2_social')->delete('verify.txt');
```

## Cost ballpark

- Storage: $0.015 / GB / month.
- Egress (Meta downloads): **free**.
- Class A operations (writes, list): $4.50 / 10M.
- Class B operations (reads, head): $0.36 / 10M.

For ~150 videos/year × 80 MB ≈ 12 GB/year. Three years of accumulation lands
around 36 GB, which costs roughly **€0.55/month**. Photo-only operation is
cents.

## Troubleshooting

**"The specified bucket does not exist"** — Bucket name in `R2_BUCKET` doesn't
match the actual bucket. Case-sensitive.

**"InvalidAccessKeyId"** — Token is for a different account or was revoked.
Regenerate.

**Public URL returns 403** — Custom domain isn't connected to the bucket, or
the certificate is still pending. Open the bucket's **Custom Domains** tab and
wait for "Active".

**Public URL returns 404 for a file you just uploaded** — Path mismatch.
Storage URLs are `<R2_PUBLIC_URL>/<path>` with no extra prefix. If you see
`flare-social/...` in the URL, the disk has `use_path_style_endpoint=false`;
flip it to `true`.

**Meta API returns "Unsupported URL scheme" or "Media URL not reachable"** —
The custom domain isn't HTTPS, or it's behind a Cloudflare Access policy
that requires authentication. Make sure the domain serves publicly with no
auth gate.

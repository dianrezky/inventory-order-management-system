[Project README](../../README.md) / Operations / MinIO VPS Integration

# Connect IOMS to MinIO on an existing VPS

IOMS already uploads product images through `ImageUploadService` and `MinioClient`. An external MinIO deployment on a VPS is supported; no additional local MinIO service is required.

## Application configuration

Set these values in the untracked `.env` file of the checkout used by the running application. Replace the example hostname, bucket and credentials with your deployment values:

```dotenv
MINIO_ENDPOINT=https://s3.example.com
MINIO_PUBLIC_URL=https://s3.example.com
MINIO_REGION=us-east-1
MINIO_ACCESS_KEY=your_ioms_access_key
MINIO_SECRET_KEY=your_ioms_secret_key
MINIO_BUCKET=portfolio-uploads
```

- `MINIO_ENDPOINT`: S3 API base URL reachable by PHP/the application container. Use the API endpoint, not the MinIO Console login URL. Do not append the bucket or an `/s3` path.
- `MINIO_PUBLIC_URL`: base URL reachable by the user's browser. The application appends the bucket and object key. This can equal `MINIO_ENDPOINT` when both PHP and the browser use the public API domain.
- `MINIO_REGION`: the region configured on the MinIO server; `us-east-1` is the application default.
- `MINIO_ACCESS_KEY` / `MINIO_SECRET_KEY`: credentials for an application account with object access to the chosen bucket. Keep them out of Git, screenshots and frontend code.
- `MINIO_BUCKET`: the existing bucket name. IOMS does not create the bucket automatically. The default is `portfolio-uploads` and object keys start with `ioms/products/`.

With Docker Compose, the owner applies environment changes by recreating the application service from this checkout:

```bash
docker compose up -d --no-deps --force-recreate app
```

A simple container restart does not reload changed Compose environment variables. Confirm the running container actually mounts this checkout; a container bound to another clone will not use edits in this one. Without Docker, restart/reload the PHP process as appropriate for its configuration.

## VPS endpoint and permissions

Expose the S3 API using a reachable HTTPS domain and a valid certificate. MinIO normally uses port 9000 for its S3 API and a separate Console port, commonly 9001. A reverse proxy must preserve the signed host/path and route the API at the domain root. See the [official endpoint reference](https://docs.min.io/aistor/reference/aistor-server/http-endpoints/) and [NGINX proxy guidance](https://docs.min.io/aistor/integrations/network-load-balancers/nginx/).

The application credentials need `s3:PutObject`, `s3:GetObject` and `s3:DeleteObject` for the IOMS product prefix. Account/policy provisioning is performed by the VPS owner, using the existing MinIO administration mechanism.

The current product-image workflow calls `publicUrl()` and stores a full URL in `products.image_path`; it does not generate expiring URLs for product images. Permit anonymous `s3:GetObject` on the image prefix if these images are intended to be public. Limit that policy to `ioms/products/`, particularly when the bucket also stores private files. Do not replace the existing bucket policy without preserving unrelated rules. See [MinIO anonymous policies](https://docs.min.io/aistor/reference/cli/mc-anonymous/).

The expected browser URL is:

```text
https://s3.example.com/portfolio-uploads/ioms/products/YYYY/MM/object.webp
```

A private-only bucket requires a separate authorized image-serving design; changing environment variables alone cannot make the existing public-image flow work with it.

## Verification

1. From the application host/container, check DNS, HTTPS and the API endpoint. An optional `curl -I https://s3.example.com/minio/health/live` checks reachability only; it does not verify credentials or object permissions.
2. Upload a small valid JPEG/PNG/WebP through the existing product form. IOMS accepts at most 2 MiB and converts the image to WebP.
3. Confirm the resulting object exists under the chosen bucket's `ioms/products/` prefix.
4. Open the stored image URL in a browser without a MinIO Console session. Verify both the product list/detail image and the direct URL.
5. Verify replacement/deletion behavior on a disposable product image if permitted. Existing delete behavior is best-effort, so an application success alone does not prove the old object was removed.

Troubleshooting:

| Symptom | Check |
| --- | --- |
| Upload fails with 403 | Application key, object policy, configured region and proxy signature handling |
| Upload succeeds but image returns 403 | Anonymous read policy on the exact image prefix |
| Image points to localhost | `MINIO_PUBLIC_URL` in the running app environment |
| Connection/TLS failure | DNS, firewall, certificate chain and container connectivity |
| Old images still use an old domain | Existing `products.image_path` values are full URLs; new environment values only affect new uploads |

Keep the old hostname working or plan a reviewed URL/data migration for old images. This guide does not execute VPS changes, container recreation, bucket policy changes or database updates.

---

[Back to README](../../README.md)

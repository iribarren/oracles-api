#!/bin/sh
# Fly.io container entrypoint.
# Decodes JWT keypair from Fly secrets and writes them to disk before
# supervisord starts PHP-FPM and Nginx.
#
# Required Fly secrets:
#   JWT_PRIVATE_KEY_BASE64  — base64-encoded content of config/jwt/private.pem
#   JWT_PUBLIC_KEY_BASE64   — base64-encoded content of config/jwt/public.pem
#
# Generate and register them with:
#   openssl genrsa -out private.pem -aes256 -passout pass:"$JWT_PASSPHRASE" 4096
#   openssl rsa -pubout -in private.pem -out public.pem -passin pass:"$JWT_PASSPHRASE"
#   fly secrets set JWT_PRIVATE_KEY_BASE64="$(base64 -w0 private.pem)" -a biblioteca-api
#   fly secrets set JWT_PUBLIC_KEY_BASE64="$(base64 -w0 public.pem)"   -a biblioteca-api

set -e

JWT_DIR="/var/www/backend/config/jwt"

if [ -z "$JWT_PRIVATE_KEY_BASE64" ] || [ -z "$JWT_PUBLIC_KEY_BASE64" ]; then
    echo "ERROR: JWT_PRIVATE_KEY_BASE64 and JWT_PUBLIC_KEY_BASE64 must be set as Fly secrets." >&2
    exit 1
fi

mkdir -p "$JWT_DIR"
printf '%s' "$JWT_PRIVATE_KEY_BASE64" | base64 -d > "$JWT_DIR/private.pem"
printf '%s' "$JWT_PUBLIC_KEY_BASE64"  | base64 -d > "$JWT_DIR/public.pem"
chmod 600 "$JWT_DIR/private.pem"
chmod 644 "$JWT_DIR/public.pem"
chown www-data:www-data "$JWT_DIR/private.pem" "$JWT_DIR/public.pem"

echo "JWT keys written to $JWT_DIR"

exec "$@"

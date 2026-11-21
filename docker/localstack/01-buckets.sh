#!/usr/bin/env bash
# Executado pelo LocalStack quando fica pronto (/etc/localstack/init/ready.d).
# Cria o bucket de mídia dos estúdios. Idempotente.
set -euo pipefail

BUCKET="${STUDIO_MEDIA_BUCKET:-studio-media}"

if ! awslocal s3api head-bucket --bucket "$BUCKET" 2>/dev/null; then
  awslocal s3api create-bucket --bucket "$BUCKET"
fi

# Mídia é privada: a API entrega o logo pelo próprio endpoint, nunca por URL pública.
awslocal s3api put-public-access-block --bucket "$BUCKET" \
  --public-access-block-configuration BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true

echo "bucket ${BUCKET} pronto"

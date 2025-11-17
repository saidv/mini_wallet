FROM node:22-alpine AS base

WORKDIR /app

RUN npm install -g pnpm

COPY frontend/package.json frontend/pnpm-lock.yaml* ./

RUN pnpm install --frozen-lockfile

FROM base AS builder

WORKDIR /app

COPY frontend .

RUN pnpm build

FROM nginx:alpine AS production

COPY --from=builder /app/dist /usr/share/nginx/html

# Copy nginx configuration
COPY infra/frontend/nginx/nginx.conf /etc/nginx/nginx.conf
COPY infra/frontend/nginx/default.conf /etc/nginx/conf.d/default.conf

# Copy SSL certificates
COPY infra/frontend/certs/localhost.pem /etc/nginx/certs/localhost.pem
COPY infra/frontend/certs/localhost-key.pem /etc/nginx/certs/localhost-key.pem

EXPOSE 80 443

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider http://localhost || exit 1

CMD ["nginx", "-g", "daemon off;"]
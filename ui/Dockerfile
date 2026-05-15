# Local development: Vite dev server + bind mount (see docker-compose.yml target).
FROM node:22-alpine AS dev

WORKDIR /app
RUN apk add --no-cache libc6-compat

COPY docker-entrypoint.dev.sh /usr/local/bin/docker-entrypoint.dev.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.dev.sh

EXPOSE 5173
ENTRYPOINT ["docker-entrypoint.dev.sh"]

FROM node:22-alpine AS build

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci

COPY . .
RUN npm run build

FROM nginx:alpine AS production

COPY --from=build /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]

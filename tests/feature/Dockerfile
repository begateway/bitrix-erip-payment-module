FROM mcr.microsoft.com/playwright:v1.59.1-noble

WORKDIR /tests

COPY tests/feature/package.json tests/feature/package-lock.json ./
RUN npm ci

COPY tests/feature/bitrix-wizard.mjs ./

CMD ["node", "bitrix-wizard.mjs"]

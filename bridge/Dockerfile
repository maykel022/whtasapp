FROM node:20-alpine

WORKDIR /app

# Install build dependencies if needed
COPY package*.json ./
RUN npm install --production

COPY . .

EXPOSE 3000

VOLUME ["/app/auth_info_baileys"]

CMD ["node", "server.js"]

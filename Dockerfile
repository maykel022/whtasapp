FROM node:20-alpine

WORKDIR /app

# Install dependencies
COPY package*.json ./
RUN npm install --production

# Copy application source code
COPY . .

EXPOSE 3000

VOLUME ["/app/auth_info_baileys"]

CMD ["node", "server.js"]

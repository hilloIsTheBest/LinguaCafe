
FROM node:20-alpine
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm install --production
COPY src ./src
ENV PORT=3456
EXPOSE 3456
CMD ["npm", "start"]

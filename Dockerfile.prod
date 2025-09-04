# Dockerfile.prod
# Use the official NGINX image
FROM nginx:alpine

# Copy the production HTML files into the default NGINX public directory
COPY ./web /usr/share/nginx/html

# Expose port 80 (the default port NGINX listens on)
EXPOSE 80

# Start NGINX (this is the default behavior, no CMD or ENTRYPOINT needed)
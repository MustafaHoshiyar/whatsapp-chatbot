# Use the official PHP + Apache image
FROM php:8.2-apache

# Copy your PHP folder into Apache's web root
COPY WhatsappChatbot/ /var/www/html/

# Expose port 80
EXPOSE 80

# 🏨 Hotel Booking System – Laravel API

This is a full-featured backend API for a hotel booking system built with **Laravel**. It includes user authentication, room & service management, image uploads with AWS S3, Stripe payment integration, and social login using Laravel Socialite.

---

## 🚀 Features

- 🔐 **Authentication** with Sanctum (token-based)
- 🏨 **Room & Service Management**
- 📷 **Image Uploads to Amazon S3**
- 💳 **Stripe Payment Integration**
- 🔗 **Social Login** (Google, Facebook) via Laravel Socialite
- 🔧 Built with **Laravel 10+**, PHP-FPM, MySQL, Docker-ready

---

## 🧰 Tech Stack

| Layer      | Technology                          |
|------------|--------------------------------------|
| Backend    | Laravel (PHP Framework)              |
| Auth       | Laravel Sanctum & Socialite          |
| Database   | MySQL (via Railway)                  |
| Cloud      | Amazon S3 for image storage          |
| Payments   | Stripe API                           |
| Server     | PHP-FPM in Docker                    |

---

## 📂 Project Structure

├── app/
├── config/
├── database/
├── public/
├── routes/
├── storage/
├── .env
├── Dockerfile
├── composer.json
└── README.md

---

## ⚙️ Setup & Installation

### 1. Clone the Repository

```bash
git clone https://github.com/yourname/hotel-booking-api.git
cd hotel-booking-api
```

## Install Dependencies
```bash
composer install
```

## Set Up Environment
```bash
cp .env.example .env
```

### Then configure your environment:
```ini
APP_NAME="Hotel"
APP_ENV="local"
APP_KEY=...
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_user
DB_PASSWORD=your_password

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your_bucket_name
AWS_USE_PATH_STYLE_ENDPOINT=true

STRIPE_KEY=your_stripe_key
STRIPE_SECRET=your_stripe_secret

GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT=http://localhost:8000/api/auth/google/callback
```

## Run Migrations
```bash
php artisan migrate --seed
```

## Run the Server
```bash
php artisan serve
```

## 🐳 Docker Setup (Optional)
```bash
docker build -t hotel-backend .
docker run -p 8000:8000 hotel-backend
```

## 📦 API Endpoints (Examples)

```bash
Method	Endpoint	                Description
POST	/api/register	            User registration
POST	/api/login	                User login
GET	    /api/rooms	                Get all rooms
POST	/api/image/upload	        Upload image to S3
POST	/api/checkout	            Make payment via Stripe
GET	    /api/auth/google/redirect	Social login (Google)
GET	    /api/auth/google/callback	Social login callback
```

## 🔐 Authentication
- Token-based using Laravel Sanctum
- Social Login via OAuth 2.0 (Laravel Socialite)

## 📤 Image Uploads
- Images are stored in Amazon S3 using Laravel's filesystem integration.

## 💳 Payment Integration
- Stripe handles payments via the official Stripe PHP SDK.

## 🤝 Contributions
- Pull requests are welcome. For major changes, please open an issue first.


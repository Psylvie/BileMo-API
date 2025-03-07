[![Codacy Badge](https://app.codacy.com/project/badge/Grade/d96ce72044914f8088b0db55707da2f1)](https://app.codacy.com/gh/Psylvie/BileMo-API/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)
# 📚 BileMo Catalog Management API  
**BileMo** is a company offering a selection of high-end mobile phones. This project represents BileMo's digital showcase, where the company exposes its product catalog via an API for business-to-business (B2B) clients.

# 📱 Features
The first client has signed a partnership agreement with BileMo. The goal is to expose several APIs that allow the partner to access data about BileMo's products and manage users. The identified requirements include:

- **View the list of BileMo products.**
- **View the details of a specific BileMo product.**
- **View the list of users associated with a client.**
- **View details of a specific user associated with a client.**
- **Add a new user for a client.**
- **Delete a user added by a client.**

# ⚙️ Installation
- **PHP**  8.2.12 or higher
- **Symfony** 7.2
- **MySQL** 5.7.34 or higher
- **JWT** for authentication

# How to use
## 1.  Clone the repo ##
``` bash
  git clone https://github.com/Psylvie/BileMo-API.git
```

## 2. Install dependencies ##
``` bash
  composer install
```
## 3.  Create your database ##
-> Database configuration Open the .env file and write your username and password configuration
 ``` bash
    DATABASE_URL="postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8"
 ```
-> In your terminal :
  ``` bash
     php bin/console doctrine:database:create
 ```

``` bash
   php bin/console doctrine:migrations:migrate
   ```

-> Load the fixtures
   ``` bash
      php bin/console doctrine:fixtures:load 
   ```
-> Start the Symfony server

   ``` bash
     symfony server:start
   ```
## 4.  🔐 LexikJWT Authentication Bundle ##
-> Install the  LexikJWT Authentication Bundle:
  ``` bash
    composer require lexik/jwt-authentication-bundle
 ```
-> Generate JWT keys:
``` bash
   php bin/console lexik:jwt:generate-keypair
 ```

💡 ⚠️ If you encounter an error, ensure OpenSSL is installed on your system. You can check this with openssl version.

-> Configure the environment file

    JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
    JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
    JWT_PASSPHRASE=your_secure_passphrase

Replace "your_secure_passphrase" with a strong and unique passphrase.

-> Update config/packages/lexik_jwt_authentication.yaml file


    lexik_jwt_authentication:
        secret_key: '%env(resolve:JWT_SECRET_KEY)%'
        public_key: '%env(resolve:JWT_PUBLIC_KEY)%'
        pass_phrase: '%env(JWT_PASSPHRASE)%'
              


# 🔑 Admin Account Creation #
To create an admin account, use the following command:
``` bash
    php bin/console app:create-admin
``` 
# 📃 API Documentation #

📌API documentation for companies (B2B customers):

🔗   http://localhost:8000/api/doc

Connection information format for a company:
- Email : company{i}@test.com  (where {i} represents a number between 0 and 19)
- Password : password123

📌 API documentation for administrators:

🔗   http://localhost:8000/api/doc/admin


# 👤 Authentication  #
The API uses JWT for authentication. To authenticate:

###  🎟️ Obtain a JWT Token ###

- **For Companies** : POST /api/login_check with company{i}@test.com and password123.

- **For Administrators**: POST /admin/login_check with your admin credentials.


### 🎟️ Use the JWT token ###

Include the JWT token in the Authorization header for each API request.


# 🛠️ Support & contact
For any questions or suggestions regarding this project, feel free to contact me via email at the following address: peuzin.sylvie.sp@gmail.com

I am open to any ideas for improvements or additional features.

# 🙇 Author #
<p text align= center> Sylvie PEUZIN  
<br> Développeuse d'application PHP/SYMFONY  


LinkedIn: [@sylvie Peuzin](https://www.linkedin.com/in/sylvie-peuzin/) </p>

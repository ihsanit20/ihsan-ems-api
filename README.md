# 🎓 Ihsan EMS API

A comprehensive **Multi-Tenant School Management System** built with Laravel 12, designed to manage multiple educational institutions from a single codebase with complete data isolation.

## 🌟 Features

### Core Functionality

- 🏫 **Multi-Tenancy** - Database-per-tenant architecture for complete data isolation
- 👥 **User Management** - Role-based access control (Developer, Owner, Admin, Teacher, Accountant, Guardian, Student)
- 📚 **Academic Management** - Sessions, Levels, Grades, Subjects, Sections
- 🎓 **Student Management** - Enrollment, Attendance, Academic records
- 💰 **Fee Management** - Fee structure, Invoices, Payments, Discounts
- 📝 **Admission System** - Online application portal with form builder
- 📊 **Reports & Analytics** - Student performance, Fee collection, Attendance

### Technical Features

- 🔐 **Sanctum Authentication** - Token-based API authentication
- 🛡️ **Rate Limiting** - Protection against abuse
- 🌐 **RESTful API** - Clean, versioned API design
- 📱 **SPA Ready** - Vue 3 + Inertia.js frontend
- ☁️ **S3 Storage** - Cloud storage for files and images
- 🔍 **Advanced Search** - Filter and search capabilities

---

## 📋 Requirements

- PHP 8.2+
- MySQL 8.0+
- Composer 2.x
- Node.js 18+ & NPM/Yarn
- Laravel 12

---

## 🚀 Quick Start

### 1. Clone & Install

```bash
# Clone repository
git clone https://github.com/mahdihasan28/ihsan-ems-api.git
cd ihsan-ems-api

# Install PHP dependencies
composer install

# Install Node dependencies
npm install
```

### 2. Environment Setup

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure your database in .env:
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ihsan_ems_api
DB_USERNAME=root
DB_PASSWORD=

# Tenant database credentials (fallback)
TENANT_DB_HOST=127.0.0.1
TENANT_DB_USERNAME=root
TENANT_DB_PASSWORD=

# Central domains (for admin panel)
CENTRAL_DOMAINS=localhost,127.0.0.1
```

### 3. Database Setup

```bash
# Run central database migrations
php artisan migrate

# Create first super admin (optional)
php artisan tinker
>>> \App\Models\User::create([
    'name' => 'Super Admin',
    'email' => 'admin@example.com',
    'password' => bcrypt('password'),
    'is_super_admin' => true
]);
```

### 4. Run Development Server

```bash
# Start Laravel server
php artisan serve

# In another terminal, start Vite
npm run dev

# Access:
# Central Admin: http://localhost:8000
# API: http://localhost:8000/api/v1
```

---

## 🏢 Tenant Management

### Creating a Tenant (School)

```bash
# Via Tinker
php artisan tinker

>>> $tenant = \App\Models\Tenant::create([
    'name' => 'Dhaka Model School',
    'domain' => 'dhaka-model.test',
    'db_name' => 'tenant_dhaka_model',
    'is_active' => true,
]);

# Run tenant migrations
php artisan db:seed --class=TenantDatabaseSeeder --tenant=$tenant->id
```

### Accessing Tenant API

All tenant endpoints require either:

- **Header:** `X-Tenant-Domain: dhaka-model.test`
- **Query:** `?tenant=dhaka-model.test`

Example:

```bash
curl http://localhost:8000/api/v1/students?tenant=dhaka-model.test \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 🔐 Authentication

### Login (Tenant User)

```bash
POST /api/v1/auth/login
Content-Type: application/json
X-Tenant-Domain: dhaka-model.test

{
  "email": "teacher@school.com",
  "password": "password"
}

# Response:
{
  "token": "1|abc123...",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "teacher@school.com",
    "role": "Teacher"
  }
}
```

### Using Token

```bash
GET /api/v1/students
Authorization: Bearer 1|abc123...
X-Tenant-Domain: dhaka-model.test
```

---

## 📡 API Endpoints

### Public Endpoints (No Auth)

```
GET  /api/v1/ping                          - Health check
GET  /api/v1/sessions                      - Academic sessions
GET  /api/v1/levels                        - Education levels
GET  /api/v1/grades                        - Grade/Class list
GET  /api/v1/subjects                      - Subject list
POST /api/v1/admission-applications        - Submit admission
```

### Authenticated Endpoints

#### Students

```
GET    /api/v1/students                    - List students
POST   /api/v1/students                    - Create student
GET    /api/v1/students/{id}               - Student details
PUT    /api/v1/students/{id}               - Update student
DELETE /api/v1/students/{id}               - Delete student
```

#### Fees & Payments

```
GET    /api/v1/fees                        - Fee types
GET    /api/v1/student-fees                - Student fee assignments
POST   /api/v1/student-fees                - Assign fee to student
GET    /api/v1/fee-invoices                - Fee invoices
POST   /api/v1/fee-invoices                - Create invoice
POST   /api/v1/payments                    - Record payment
```

#### Academic

```
GET    /api/v1/sessions/{id}/classes       - Classes in session
GET    /api/v1/sections                    - Sections
GET    /api/v1/session-subjects            - Subject assignments
```

#### Users

```
GET    /api/v1/users                       - List users
POST   /api/v1/users                       - Create user
PUT    /api/v1/users/{id}                  - Update user
```

**📖 Full API Documentation:** [API.md](API.md)

---

## 👥 User Roles & Permissions

### Role Hierarchy

1. **Developer** - Full system access (super admin)
2. **Owner** - School owner (all tenant operations)
3. **Admin** - School administrator
4. **Teacher** - Academic operations
5. **Accountant** - Financial operations
6. **Guardian** - View student information
7. **Student** - Limited access to own data

### Permission Levels

- `DEV_ONLY` - Developer only
- `OWNER_PLUS` - Owner and above
- `ADMIN_PLUS` - Admin and above
- `TEACHER_PLUS` - Teacher and above
- `ACCOUNTANT_PLUS` - Accountant and above
- `GUARDIAN_PLUS` - Guardian and above
- `STUDENT_PLUS` - All authenticated users

---

## 🗄️ Database Architecture

### Central Database

- `users` - Super admins
- `tenants` - School/institution records
- `personal_access_tokens` - API tokens
- System tables (cache, jobs, sessions)

### Tenant Database (per school)

- `users` - School staff and students
- `academic_sessions` - School years
- `levels` - Education levels (Primary, Secondary, etc.)
- `grades` - Classes/Grades
- `students` - Student records
- `student_enrollments` - Class enrollments
- `fees` - Fee types
- `session_fees` - Fee assignments per session/grade
- `student_fees` - Individual student fees
- `fee_invoices` - Generated invoices
- `payments` - Payment records
- `admission_applications` - Admission forms

---

## 🧪 Testing

### Run Tests

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run with coverage
php artisan test --coverage

# Run specific test file
php artisan test tests/Feature/TenantIsolationTest.php
```

### Test Database Setup

Tests use a separate database. Configure in `.env.testing`:

```env
DB_DATABASE=ihsan_ems_testing
```

---

## 🛠️ Development

### Code Style

```bash
# Format code (Pint)
./vendor/bin/pint

# Check code style
./vendor/bin/pint --test

# Frontend linting
npm run lint
npm run format
```

### Database Operations

```bash
# Fresh migration
php artisan migrate:fresh

# Seed database
php artisan db:seed

# Rollback last migration
php artisan migrate:rollback

# Reset all & seed
php artisan migrate:fresh --seed
```

### Tenant Operations

```bash
# Run migration for specific tenant
php artisan tenants:migrate --tenant=1

# Seed specific tenant
php artisan tenants:seed --tenant=1
```

---

## 📦 Deployment

### Production Checklist

- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Configure real database credentials
- [ ] Setup S3 storage (AWS)
- [ ] Configure mail server (SMTP)
- [ ] Setup queue worker (supervisor)
- [ ] Enable HTTPS/SSL
- [ ] Configure CORS properly
- [ ] Setup backups (database + files)
- [ ] Configure error monitoring (Sentry)
- [ ] Run `composer install --optimize-autoloader --no-dev`
- [ ] Run `npm run build`
- [ ] Run `php artisan config:cache`
- [ ] Run `php artisan route:cache`
- [ ] Run `php artisan view:cache`

### Deployment Commands

```bash
# Optimize for production
php artisan optimize

# Clear all caches
php artisan optimize:clear

# Link storage
php artisan storage:link
```

---

## 🔧 Configuration

### Key Configuration Files

- `.env` - Environment variables
- `config/tenancy.php` - Multi-tenancy settings
- `config/database.php` - Database connections
- `config/sanctum.php` - API authentication
- `config/filesystems.php` - Storage configuration

### Environment Variables

```env
# App
APP_NAME="Ihsan EMS"
APP_ENV=local|production
APP_DEBUG=true|false
APP_URL=https://your-domain.com

# Tenancy
CENTRAL_DOMAINS=localhost,admin.yourdomain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=ihsan_ems_api

# Tenant Fallback
TENANT_DB_HOST=127.0.0.1
TENANT_DB_USERNAME=root

# AWS S3 (for file uploads)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
```

---

## 🐛 Troubleshooting

### Common Issues

**Issue:** "Tenant not found"

```bash
# Check tenant domain matches exactly
# Verify tenant is active: is_active = true
```

**Issue:** "Table not found"

```bash
# Run tenant migrations
php artisan migrate --path=database/migrations/tenant
```

**Issue:** "Connection refused"

```bash
# Check MySQL is running
# Verify database credentials in .env
```

**Issue:** "Composer memory limit"

```bash
COMPOSER_MEMORY_LIMIT=-1 composer install
```

---

## 📚 Resources

- [Laravel Documentation](https://laravel.com/docs/12.x)
- [Vue 3 Documentation](https://vuejs.org/)
- [Inertia.js Documentation](https://inertiajs.com/)
- [Tailwind CSS](https://tailwindcss.com/)

---

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Coding Standards

- Follow PSR-12 coding standards
- Write meaningful commit messages
- Add tests for new features
- Update documentation

---

## 📄 License

This project is proprietary software. All rights reserved.

---

## 👨‍💻 Support

For support, email support@ihsan-ems.com or open an issue in the repository.

---

## 🎯 Roadmap

### Version 1.0 (Current)

- ✅ Multi-tenancy architecture
- ✅ Student management
- ✅ Fee management
- ✅ Admission system

### Version 1.1 (Planned)

- [ ] Attendance tracking
- [ ] Exam management
- [ ] Grade book
- [ ] Parent portal

### Version 2.0 (Future)

- [ ] Mobile app (Flutter)
- [ ] SMS notifications
- [ ] Online classes integration
- [ ] Advanced analytics dashboard

---

**Made with ❤️ for Education**

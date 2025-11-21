# 📡 API Documentation

## Base URL

```
http://localhost:8000/api/v1
```

## Authentication

All authenticated endpoints require a Bearer token in the Authorization header:

```http
Authorization: Bearer YOUR_TOKEN_HERE
X-Tenant-Domain: your-school.test
```

---

## 🔐 Authentication Endpoints

### Login

```http
POST /api/v1/auth/login
X-Tenant-Domain: school.test
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password"
}
```

**Response:**

```json
{
    "token": "1|abcdef123456...",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "user@example.com",
        "role": "Teacher"
    }
}
```

### Get Current User

```http
GET /api/v1/me
Authorization: Bearer TOKEN
X-Tenant-Domain: school.test
```

### Logout

```http
POST /api/v1/auth/logout
Authorization: Bearer TOKEN
X-Tenant-Domain: school.test
```

---

## 👨‍🎓 Students

### List Students

```http
GET /api/v1/students
Authorization: Bearer TOKEN
X-Tenant-Domain: school.test

Query Parameters:
- q: Search term (name, code, phone)
- status: active|inactive|passed|tc_issued|dropped
- gender: male|female|other
- academic_session_id: Filter by session
- session_grade_id: Filter by grade
- section_id: Filter by section
- per_page: Number per page (default: 25)
- with_latest_enrollment: true|false
```

**Response:**

```json
{
  "data": [
    {
      "id": 1,
      "name_en": "John Doe",
      "name_bn": "জন ডো",
      "student_code": "STU-2024-001",
      "father_name": "Father Name",
      "date_of_birth": "2010-01-01",
      "gender": "male",
      "status": "active",
      "created_at": "2024-01-01T00:00:00Z"
    }
  ],
  "links": {...},
  "meta": {...}
}
```

### Create Student

```http
POST /api/v1/students
Authorization: Bearer TOKEN
X-Tenant-Domain: school.test
Content-Type: application/json

{
  "name_en": "John Doe",
  "name_bn": "জন ডো",
  "father_name": "Father Name",
  "mother_name": "Mother Name",
  "date_of_birth": "2010-01-01",
  "gender": "male",
  "blood_group": "A+",
  "religion": "Islam",
  "nationality": "Bangladeshi",
  "student_phone": "01712345678",
  "father_phone": "01812345678",
  "guardian_phone": "01912345678",
  "present_address": "Dhaka, Bangladesh",
  "permanent_address": "Dhaka, Bangladesh"
}
```

### Get Student Details

```http
GET /api/v1/students/{id}
Authorization: Bearer TOKEN
X-Tenant-Domain: school.test
```

### Update Student

```http
PUT /api/v1/students/{id}
Authorization: Bearer TOKEN
X-Tenant-Domain: school.test
Content-Type: application/json

{
  "name_en": "Updated Name",
  "status": "active"
}
```

### Delete Student

```http
DELETE /api/v1/students/{id}
Authorization: Bearer TOKEN
X-Tenant-Domain: school.test
```

---

## 💰 Fee Management

### List Fees

```http
GET /api/v1/fees
X-Tenant-Domain: school.test

Query Parameters:
- q: Search by name
- billing_type: one_time|recurring
- is_active: true|false
```

### List Student Fees

```http
GET /api/v1/student-fees
Authorization: Bearer TOKEN
X-Tenant-Domain: school.test

Query Parameters:
- student_id: Filter by student
- academic_session_id: Filter by session
- session_fee_id: Filter by fee type
```

### Assign Fee to Student

```http
POST /api/v1/student-fees
Authorization: Bearer TOKEN
X-Tenant-Domain: school.test
Content-Type: application/json

{
  "student_id": 1,
  "academic_session_id": 1,
  "session_fee_id": 1,
  "amount": 5000,
  "discount_type": "flat",
  "discount_value": 500
}
```

### Assign Multiple Fees

```http
POST /api/v1/student-fees
Authorization: Bearer TOKEN
X-Tenant-Domain: school.test
Content-Type: application/json

{
  "student_id": 1,
  "academic_session_id": 1,
  "items": [
    {
      "session_fee_id": 1,
      "amount": 5000,
      "discount_type": "percent",
      "discount_value": 10
    },
    {
      "session_fee_id": 2,
      "amount": 2000
    }
  ]
}
```

---

## 🧾 Fee Invoices

### List Invoices

```http
GET /api/v1/fee-invoices
Authorization: Bearer TOKEN
X-Tenant-Domain: school.test

Query Parameters:
- student_id: Filter by student
- academic_session_id: Filter by session
- status: pending|partial|paid|cancelled
- invoice_no: Search by invoice number
```

### Create Invoice

```http
POST /api/v1/fee-invoices
Authorization: Bearer TOKEN
X-Tenant-Domain: school.test
Content-Type: application/json

{
  "student_id": 1,
  "academic_session_id": 1,
  "invoice_date": "2024-01-01",
  "due_date": "2024-01-31",
  "items": [
    {
      "student_fee_id": 1,
      "amount": 5000,
      "discount_amount": 500
    }
  ]
}
```

### Get Invoice Details

```http
GET /api/v1/fee-invoices/{id}
Authorization: Bearer TOKEN
X-Tenant-Domain: school.test
```

---

## 💳 Payments

### List Payments

```http
GET /api/v1/payments
Authorization: Bearer TOKEN
X-Tenant-Domain: school.test

Query Parameters:
- student_id: Filter by student
- fee_invoice_id: Filter by invoice
- date_from: Start date (YYYY-MM-DD)
- date_to: End date (YYYY-MM-DD)
- method: cash|bank|online
- status: completed|pending|failed
```

### Record Payment

```http
POST /api/v1/payments
Authorization: Bearer TOKEN
X-Tenant-Domain: school.test
Content-Type: application/json

{
  "student_id": 1,
  "fee_invoice_id": 1,
  "payment_date": "2024-01-15",
  "method": "cash",
  "amount": 4500,
  "reference_no": "TXN123456"
}
```

---

## 📚 Academic

### List Sessions

```http
GET /api/v1/sessions
X-Tenant-Domain: school.test

Query Parameters:
- active: true|false
- paginate: true|false
```

### List Levels

```http
GET /api/v1/levels
X-Tenant-Domain: school.test

Query Parameters:
- is_active: true|false
```

### List Grades

```http
GET /api/v1/grades
X-Tenant-Domain: school.test

Query Parameters:
- level_id: Filter by level
- is_active: true|false
```

### List Classes in Session

```http
GET /api/v1/sessions/{session_id}/classes
X-Tenant-Domain: school.test
```

### List Sections

```http
GET /api/v1/sections?session_grade_id=1
X-Tenant-Domain: school.test
```

### List Subjects

```http
GET /api/v1/subjects
X-Tenant-Domain: school.test

Query Parameters:
- grade_id: Filter by grade
- only_active: true|false
```

---

## 👥 User Management

### List Users

```http
GET /api/v1/users
Authorization: Bearer TOKEN
X-Tenant-Domain: school.test

Query Parameters:
- role: Developer|Owner|Admin|Teacher|Accountant|Guardian|Student
- q: Search by name or email
```

### Create User

```http
POST /api/v1/users
Authorization: Bearer TOKEN
X-Tenant-Domain: school.test
Content-Type: application/json

{
  "name": "Teacher Name",
  "email": "teacher@school.com",
  "password": "password",
  "role": "Teacher"
}
```

### Update User

```http
PUT /api/v1/users/{id}
Authorization: Bearer TOKEN
X-Tenant-Domain: school.test
Content-Type: application/json

{
  "name": "Updated Name",
  "email": "updated@school.com",
  "role": "Admin"
}
```

---

## 📝 Admission Applications

### Submit Application

```http
POST /api/v1/admission-applications
X-Tenant-Domain: school.test
Content-Type: application/json

{
  "academic_session_id": 1,
  "session_grade_id": 1,
  "applicant_name": "Student Name",
  "father_name": "Father Name",
  "mother_name": "Mother Name",
  "date_of_birth": "2010-01-01",
  "gender": "male",
  "religion": "Islam",
  "student_phone": "01712345678",
  "guardian_phone": "01812345678",
  "present_address": "Dhaka"
}
```

### List Applications (Admin)

```http
GET /api/v1/admission-applications
Authorization: Bearer TOKEN
X-Tenant-Domain: school.test

Query Parameters:
- academic_session_id: Filter by session
- session_grade_id: Filter by grade
- status: pending|accepted|rejected|admitted
- search: Search term
```

---

## 🌍 Address Data

### List Divisions

```http
GET /api/v1/divisions
X-Tenant-Domain: school.test
```

### List Districts

```http
GET /api/v1/districts?division_id=1
X-Tenant-Domain: school.test
```

### List Areas

```http
GET /api/v1/areas?district_id=1
X-Tenant-Domain: school.test
```

---

## 📊 Response Format

### Success Response

```json
{
  "data": {...},
  "message": "Success message"
}
```

### Error Response

```json
{
    "message": "Error message",
    "errors": {
        "field": ["Validation error"]
    }
}
```

### Pagination Response

```json
{
  "data": [...],
  "links": {
    "first": "url",
    "last": "url",
    "prev": null,
    "next": "url"
  },
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 75
  }
}
```

---

## 🔒 Role-Based Access

| Endpoint         | Developer | Owner | Admin | Teacher | Accountant | Guardian | Student |
| ---------------- | --------- | ----- | ----- | ------- | ---------- | -------- | ------- |
| Students (Read)  | ✅        | ✅    | ✅    | ✅      | ✅         | ✅       | ✅      |
| Students (Write) | ✅        | ✅    | ✅    | ❌      | ❌         | ❌       | ❌      |
| Fees (Read)      | ✅        | ✅    | ✅    | ✅      | ✅         | ✅       | ✅      |
| Fees (Write)     | ✅        | ✅    | ✅    | ❌      | ✅         | ❌       | ❌      |
| Payments         | ✅        | ✅    | ✅    | ❌      | ✅         | ❌       | ❌      |
| Users            | ✅        | ✅    | ✅    | ❌      | ❌         | ❌       | ❌      |

---

## 📝 Notes

- All dates are in `YYYY-MM-DD` format
- All timestamps are in ISO 8601 format
- File uploads use `multipart/form-data`
- Maximum file size: 10MB
- Rate limit: 60 requests/minute (authenticated)
- Rate limit: 20 requests/minute (auth endpoints)

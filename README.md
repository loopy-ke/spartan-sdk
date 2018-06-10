# spartan-sdk
An easier way of accessing spartan realm eco services

## Core fields are
```
    1. names
    2. saluatation
    3. gender
    4. dob
    5. email
    6. phone_a
    7. phone_b
```

## Creating a member

POST /member/id/number

amending a meber
PATCH /member/internal_id
Attaching a file 
POST /file
attaching a photo
POST photo/internal_id

Searching a member
GET /search ?  creteria names,gender,dob,email,phone_a,phone_b

get a file 

Attach IDs
POST /member/type/internal_id

PATCH IDs 
/member/type/internal_id

Where ids are key value pairs of ID Types and Numbers


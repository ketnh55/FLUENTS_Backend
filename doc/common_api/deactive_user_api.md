# Deactive user api

| attribute | value |
|-----------|-------|
| version   | 1.0   |
| creator   | ket2.nguyen.huu@gmail.com |
| created   | 2019-04-30 |
| updater   | 
| updated   |  |

## 1. Overview 

- A API allow user close account.

## 2. Endpoint

- */api/v1/deactive_user_api*

## 3. Method

- GET

## 4.Input 

name  | description| format | type | range | required
--- | ---| ---| ---|---|---


## 5.Example API Call

- Method : GET

- Header: 
    - X-Requested-With: XMLHttpRequest
    
    - Authorization : '"eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOjI0LCJpc3MiOiJodHRwOi8vbG9jYWxob3N0OjgwMDAvYXBpL3YxL3VzZXJfbG9naW5fYXBpIiwiaWF0IjoxNTUzNDE5OTM2LCJleHAiOjE1NTM0MjM1MzYsIm5iZiI6MTU1MzQxOTkzNiwianRpIjoib1hDOE41UW12cEtBNUtCZSJ9.GPau62lF2scfzub6cHmlQx40yxjxTlmSKs1W7G9F1ws',        
        
- Url : *http://domain_name/api/v1/deactive_user_api/*

## 6. Diagram 

- N/A

## 7. Action

- Step 1 : Validate jwt token  parameter
    + If not valid, return error message
        + Error message type: 
            + Lack jwt authentication in request
    ↓       + Jwt token expired
            + Jwt token is not valid
            + Jwt user not found

- Step 2 : Check if user already deactive or not
    + Yes: Return error
    + No: Go to step 3

- Step 3: Deactive user and return result

## 8. Output

- Deactive user success or fail  

## 9. Example Response 

- HTTP Code : 200

- JSON response 
    
    + Success:
    
    ```
    {
        "error": "User is deactivated"
    }
    ```
    
    + Failed: 
    
    ```
    {
        "error": "token_expired"
        //"error": "User is deactivated"
        //"deactive": "success"
    }
    ```

## 10. Exception

- Return error message if jwt token is not valid 
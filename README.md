## Dependencies
Written with php 8.2.3 and apache 2.4.38

Install [composer](https://getcomposer.org/) dependencies in root of project

## Database
create SQLite database file named "brc.db" in root of project

## API Keys
create a '.env' file in the project root. required keys:

### Emails
- SENDGRID_API_KEY_ID
- SENDGRID_API_KEY

### Google Sign-in button
also requires configuring OAuth consent screen
- GOOGLE_CLIENT_ID
- GOOGLE_CLIENT_SECRET

### Airtable sync
- AIRTABLE_ACESS_TOKEN
# Shopify Production Authentication gotchas & 401 Unauthorized Fix

This document records a critical troubleshooting session regarding Shopify OAuth, specifically why checkout redirects (draft order creation) failed on the production server while working fine locally.

---

## 1. Symptom
- Customers clicking checkout on the storefront widget experienced redirect failures.
- Production logs showed `401 Unauthorized` responses from Shopify's draft order endpoint:
  ```json
  [API] Invalid API key or access token (unrecognized login or wrong password)
  ```
- Running diagnostics showed that the access token (`shpat_...`) stored in the database was rejected by Shopify.

---

## 2. Root Cause
- **Expiring Offline Tokens Requirement:** Shopify requires all apps created on or after **April 1, 2026** to use expiring offline tokens.
- **Local vs. Production Mismatch:** 
  - Locally, the `.env` file correctly had `SHOPIFY_EXPIRING_OFFLINE_TOKENS=true`.
  - On the production server, `SHOPIFY_EXPIRING_OFFLINE_TOKENS` was missing from the `.env` file, defaulting to `false`.
- **Stale Token Loop:** Because the config was `false` on production, during the OAuth flow Shopify returned an expiring token, but the app didn't save the refresh token/expiry details. Once the token expired or was invalidated, the app was stuck using the old, invalid token, causing all API requests to fail with `401`.

---

## 3. Resolution Checklist for Future Reference

If you experience `401 Unauthorized` errors after deploying or when reinstalling the app, follow these steps:

### Step 1: Verify `.env` Configuration
Ensure the following variables are present in the production `.env` file:
```env
# Required for modern Shopify Apps (post April 1, 2026)
SHOPIFY_EXPIRING_OFFLINE_TOKENS=true

# Standard App Credentials
SHOPIFY_API_KEY=your_client_id
SHOPIFY_API_SECRET=your_client_secret
SHOPIFY_API_REDIRECT=https://your-domain.com/authenticate
```

### Step 2: Clear Tokens and Config Cache
Visit the following endpoint in your browser to clear out the stale database tokens and flush Laravel's config cache:
👉 `https://your-domain.com/force-clear-all.php`

### Step 3: Trigger Reinstallation
Go to the **Shopify Partner Dashboard** or **Shopify Store Admin** and open the app. This will force Shopify to initiate the OAuth flow and generate a fresh, valid token with the correct expiration details.

### Step 4: Run Diagnostics
Verify the API connection by visiting:
👉 `https://your-domain.com/run-diagnostics.php`
*It should return `Status: 200` and `Success!`.*

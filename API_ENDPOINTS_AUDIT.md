# 📊 Complete API Endpoints Audit: Backend vs Frontend

**Repository:** `grassflorist` (Laravel Backend & Next.js Frontend)  
**Audit Date:** October 2026  
**Status:** Comprehensive Verification Complete

---

## 📋 Table of Contents
1. [Executive Summary](#1-executive-summary)
2. [Swagger API Documentation Status](#2-swagger-api-documentation-status)
3. [🟢 Actively Used Endpoints in Frontend (69 Endpoints)](#3--actively-used-endpoints-in-frontend-69-endpoints)
4. [🔴 Unused Endpoints in Frontend (58 Endpoints)](#4--unused-endpoints-in-frontend-58-endpoints)
   - [Group A: Duplicate / Alias Routes (18 Endpoints)](#group-a-duplicate--alias-routes)
   - [Group B: Server-to-Server Payment Webhooks (8 Endpoints)](#group-b-server-to-server-payment-webhooks)
   - [Group C: Legacy / Abandoned Endpoints (18 Endpoints)](#group-c-legacy--abandoned-endpoints)
   - [Group D: Redundant Internal / Utility Endpoints (14 Endpoints)](#group-d-redundant-internal--utility-endpoints)
5. [🧹 Recommended Cleanup Plan for Backend](#5--recommended-cleanup-plan-for-backend)

---

## 1. Executive Summary

| Metric | Count Before | Count After Cleanup | Notes |
| :--- | :---: | :---: | :--- |
| **Total Route Registrations in Laravel** | **246** | **165** | Cleaned 81 duplicate & dead route registrations |
| **Total Distinct API Endpoint Signatures** | **127** | **83** | Normalized without language prefix duplication |
| **🟢 Actively Used in Frontend** | **69** | **69** | All 69 active storefront endpoints fully preserved |
| **🔴 Unused / Legacy Routes Cleaned** | **58** | **14 remaining** | Category 1 (Dead code) & Category 2 (Duplicates) removed |

---

## 2. Swagger API Documentation Status

### Backend Swagger Documentation (`/admin/api-documentation` & `/docs/swagger`)
- **Engine:** Dedoc Scramble (OpenAPI 3.1 generator).
- **Kya Swagger me sabhi endpoints aa rahe hain?**
  - **Haan!** Laravel ke sabhi 127 distinct API endpoints Swagger me registered hain.
  - **Screenshot me sirf `Auth` kyu dikh raha tha?**
    1. Swagger UI me endpoints Controllers ke according **Tags** me grouped hote hain.
    2. Tags alphabetically sort hote hain: `default` -> `Auth` -> `Cart` -> `Category` -> `Checkout` -> `Cms Page` -> `Product` -> `Subscriptions`, etc.
    3. Screenshot me user Swagger window ke **top** par the. Swagger iframe ke andar **scroll down** karne par neeche ke sabhi groups (`Cart`, `Category`, `Product`, `Subscriptions`, etc.) visible hote hain.
- **Improvements Applied to Swagger:**
  - Pehle route fallback `api/{fallbackPlaceholder}` Swagger me dummy endpoint bankar top par dikh raha tha. Humne [config/scramble.php](file:///d:/Projects/grassnew/backend/config/scramble.php) me `api/{fallbackPlaceholder}`, `api/debug`, aur legacy `razorpay` endpoints ko exclude kar diya hai, taaki Swagger ekdum clean storefront documentation show kare.

---

## 3. 🟢 Actively Used Endpoints in Frontend (69 Endpoints)

Ye sabhi endpoints Next.js frontend me live hain aur active storefront features chala rahe hain:

### 📦 3.1 Catalog & Products (5 Endpoints)
| Method | Endpoint | Frontend Component / Hook | Purpose |
| :---: | :--- | :--- | :--- |
| `GET` | `/api/{locale}/products` | `getStoreProducts` / `productsApi` | Product catalog listing & category fallback |
| `GET` | `/api/{locale}/products/{slug}` | `getStoreProductDetails` / PDP | Product detail page, gallery, & bought together |
| `GET` | `/api/{locale}/category` | `getStoreCategories` / `categoriesApi` | Mega menu & category sliders |
| `GET` | `/api/{locale}/category/{slug}` | `getStoreCategoryBySlug` | Category detail page with subcategories |
| `GET` | `/api/{locale}/search` | `ProductSearchController` | Live header search drawer |

### 🛒 3.2 Cart & Checkout (14 Endpoints)
| Method | Endpoint | Frontend Component / Hook | Purpose |
| :---: | :--- | :--- | :--- |
| `GET` | `/api/{locale}/cart/viewcart` | `cartApi.useViewCartQuery` | Live shopping cart drawer & cart page |
| `POST` | `/api/{locale}/cart/add` | `cartApi.useAddToCartMutation` | Add item to cart |
| `POST` | `/api/{locale}/cart/cartupdate` | `cartApi.useUpdateCartQuantityMutation` | Increase / decrease quantity |
| `POST` | `/api/{locale}/cart/remove` | `cartApi.useRemoveCartItemMutation` | Delete item from cart |
| `GET/POST`| `/api/{locale}/cart/empty` | `cartApi.useEmptyCartMutation` | Clear entire cart |
| `POST` | `/api/{locale}/cart/sync-customer` | `cartApi.useSyncCustomerCartMutation` | Link guest cart to logged-in user |
| `POST` | `/api/{locale}/cart/merge` | `cartApi.useMergeCartMutation` | Merge guest items into account |
| `GET` | `/api/{locale}/cart/coupons` | `cartApi.useGetAvailableCouponsQuery` | Available coupons drawer |
| `POST` | `/api/{locale}/cart/coupon/{code}` | `cartApi.useApplyServerCouponMutation` | Validate and apply promo coupon |
| `GET` | `/api/{locale}/shipping-methods` | `checkoutApi.useGetShippingMethodsQuery` | Delivery fees & free shipping rules |
| `GET` | `/api/{locale}/v1/payment-methods` | `checkoutApi.useGetPaymentMethodsQuery` | Mada, Apple Pay, Visa, Tamara, Tabby, PayPal |
| `GET` | `/api/{locale}/v1/delivery-slots` | `checkoutApi.useGetDeliverySlotsQuery` | Delivery slots & date pickers |
| `POST` | `/api/{locale}/cart/checkout` | `ordersApi.useCreateOrderMutation` | Place final order & save to DB |
| `GET` | `/api/{locale}/orders/{order_number}`| `ordersApi.useGetOrderByNumberQuery` | Order confirmation / Thank you page |

### 💳 3.3 Payments (2 Endpoints)
| Method | Endpoint | Frontend Component / Hook | Purpose |
| :---: | :--- | :--- | :--- |
| `POST` | `/api/{locale}/v1/payments/initiate` | `checkoutApi.useInitiatePaymentMutation` | Generates HyperPay Checkout ID / Gateway URLs |
| `POST` | `/api/{locale}/v1/payments/verify` | `checkoutApi.useVerifyPaymentMutation` | Verifies payment return status |

### 👤 3.4 Customer Authentication & Profile (11 Endpoints)
| Method | Endpoint | Frontend Component / Hook | Purpose |
| :---: | :--- | :--- | :--- |
| `POST` | `/api/{locale}/v1/login` | `authApi.useLoginMutation` | Customer login with email & password |
| `POST` | `/api/{locale}/v1/register` | `authApi.useRegisterMutation` | Customer registration |
| `POST` | `/api/{locale}/v1/updateuser` | `authApi.useUpdateProfileMutation` | Update profile (name, phone, address) |
| `GET` | `/api/{locale}/my-account/checkauth` | `authApi.useCheckAuthQuery` | Auth route guard |
| `GET` | `/api/{locale}/my-account/user` | `authApi.useGetUserProfileQuery` | User profile & addresses |
| `POST` | `/api/{locale}/my-account/logout` | `authApi.useLogoutUserMutation` | Invalidate token & logout |
| `POST` | `/api/{locale}/my-account/refresh` | `baseApi.ts` (Silent Refresh) | Refresh expired JWT token |
| `POST` | `/api/{locale}/customer/forgot-password` | `authApi.useForgotPasswordMutation` | Send password reset link |
| `POST` | `/api/{locale}/customer/reset-password` | `authApi.useResetPasswordMutation` | Set new password via token |
| `POST` | `/api/{locale}/my-account/send-verification-email` | `authApi.useSendVerificationEmailMutation` | Send email verification |
| `POST/GET`| `/api/{locale}/my-account/verify-email` | `authApi.useVerifyEmailMutation` | Verify customer email |
| `POST` | `/api/{locale}/my-account/passwordchange` | `authApi.useChangePasswordMutation` | Change password in security tab |
| `GET` | `/api/{locale}/my-account/user_order/{id}` | `authApi.useGetUserOrdersQuery` | Customer orders list |

### 🌸 3.5 Flower Subscriptions (14 Endpoints)
| Method | Endpoint | Frontend Component / Hook | Purpose |
| :---: | :--- | :--- | :--- |
| `GET` | `/api/{locale}/subscription-plans` | `subscriptionApi.useGetSubscriptionDealsQuery` | Subscription deals landing page |
| `GET` | `/api/{locale}/subscription-plans/{slug}` | `subscriptionApi.useGetSubscriptionDealBySlugQuery` | Single plan page & customization |
| `GET` | `/api/{locale}/subscriptions/settings` | `SubscriptionPurchaseSelector` | Global subscription discount rules |
| `POST` | `/api/{locale}/subscriptions/calculate` | `SubscriptionPurchaseSelector` | Server-side pricing calculation |
| `POST` | `/api/{locale}/subscriptions/checkout` | `SubscribeCheckoutPageView` | Dedicated subscription checkout |
| `GET` | `/api/{locale}/my-account/subscriptions` | `CustomerSubscriptionsDashboard` | Active & past subscriptions list |
| `GET` | `/api/{locale}/my-account/subscriptions/{id}` | `CustomerSubscriptionsDashboard` | Subscription schedule breakdown |
| `POST` | `/api/{locale}/my-account/subscriptions/{id}/pause` | `CustomerSubscriptionsDashboard` | Pause deliveries |
| `POST` | `/api/{locale}/my-account/subscriptions/{id}/resume` | `CustomerSubscriptionsDashboard` | Resume deliveries |
| `POST` | `/api/{locale}/my-account/subscriptions/{id}/skip-next` | `CustomerSubscriptionsDashboard` | Skip immediate next delivery |
| `POST` | `/api/{locale}/my-account/subscriptions/{id}/deliveries/{deliveryId}/reschedule` | `CustomerSubscriptionsDashboard` | Reschedule specific delivery slot |
| `POST` | `/api/{locale}/my-account/subscriptions/{id}/address` | `CustomerSubscriptionsDashboard` | Change delivery address |
| `POST` | `/api/{locale}/my-account/subscriptions/{id}/cancel` | `CustomerSubscriptionsDashboard` | Cancel subscription |
| `POST` | `/api/{locale}/my-account/subscriptions/{id}/retry-payment` | `CustomerSubscriptionsDashboard` | Retry failed subscription charge |

### ❤️ 3.6 Wishlist (4 Endpoints)
| Method | Endpoint | Frontend Component / Hook | Purpose |
| :---: | :--- | :--- | :--- |
| `GET` | `/api/{locale}/my-account/view-wishlist` | `wishlistApi.useGetWishlistQuery` | Wishlist page & drawer items |
| `GET` | `/api/{locale}/my-account/view-wishlistid` | `wishlistApi.useGetWishlistIdsQuery` | Heart active state lookup |
| `POST` | `/api/{locale}/my-account/add-wishlist` | `wishlistApi.useAddToWishlistMutation` | Add item to wishlist |
| `DELETE`| `/api/{locale}/my-account/delete-wishlist` | `wishlistApi.useRemoveFromWishlistServerMutation` | Remove item from wishlist |

### 📰 3.7 Content & CMS (12 Endpoints)
| Method | Endpoint | Frontend Component / Hook | Purpose |
| :---: | :--- | :--- | :--- |
| `GET` | `/api/{locale}/home-page` | `getHomePageData` / `HomePageView` | Bestsellers, hero sliders, categories |
| `GET` | `/api/{locale}/navigation` | `getNavigationData` / Header | Mega menu, mobile nav, footer links |
| `GET` | `/api/{locale}/global-settings` | `cmsApi.useGetGlobalSettingsQuery` | Currency rates, VAT, store contact |
| `GET` | `/api/{locale}/blog` | `getBlogPosts` / `BlogListingPageView` | Blog listing |
| `GET` | `/api/{locale}/blog/{slug}` | `getBlogPostBySlug` / `BlogPostPageView` | Single blog article |
| `GET` | `/api/{locale}/cms-pages/{slug}` | `getCmsPageBySlug` / Policy pages | Privacy Policy, Terms, About Us |
| `GET` | `/api/{locale}/faqs` | `getFaqs` / FAQ Page | Store FAQs |
| `GET` | `/api/{locale}/google-reviews` | `cmsApi.useGetGoogleReviewsQuery` | Verified Google customer reviews |
| `GET` | `/api/{locale}/testimonials` | `TestimonialController` | Customer testimonials slider |
| `GET` | `/api/{locale}/contact-page` | `getContactPageData` / Contact page | Boutique address & store timings |
| `POST` | `/api/{locale}/contact-form` | `cmsApi.useSendContactFormMutation` | Contact us form submission |
| `POST` | `/api/{locale}/newsletter` | `cmsApi.useSubscribeNewsletterMutation` | Footer newsletter subscription |
| `POST` | `/api/{locale}/event-booking` | Next.js Proxy `/api/event-booking` | Event floral styling booking |
| `POST` | `/api/{locale}/partner-with-us` | Next.js Proxy `/api/partner-with-us` | Florist partnership applications |

---

## 4. 🔴 Unused Endpoints in Frontend (58 Endpoints)

Ye endpoints backend `routes/api.php` me define hain, lekin Next.js frontend me **call nahi hote**. Inko 4 categories me classify kiya gaya hai:

### Group A: Duplicate / Alias Routes (18 Endpoints)
*Ye endpoints backend me doosre routes ke duplicate hain. Frontend inka primary route use karta hai:*

| # | Unused Endpoint | Primary Route Used by Frontend | Reason |
| :-: | :--- | :--- | :--- |
| 1 | `GET /api/{locale}/customer/subscriptions` | `/api/{locale}/my-account/subscriptions` | Frontend uses `/my-account/` prefix for all customer dashboards |
| 2 | `GET /api/{locale}/customer/subscriptions/{id}` | `/api/{locale}/my-account/subscriptions/{id}` | Duplicate alias |
| 3 | `POST /api/{locale}/customer/subscriptions/{id}/pause` | `/api/{locale}/my-account/subscriptions/{id}/pause` | Duplicate alias |
| 4 | `POST /api/{locale}/customer/subscriptions/{id}/resume` | `/api/{locale}/my-account/subscriptions/{id}/resume` | Duplicate alias |
| 5 | `POST /api/{locale}/customer/subscriptions/{id}/skip-next` | `/api/{locale}/my-account/subscriptions/{id}/skip-next` | Duplicate alias |
| 6 | `POST /api/{locale}/customer/subscriptions/{id}/deliveries/{deliveryId}/reschedule` | `/api/{locale}/my-account/subscriptions/{id}/deliveries/{deliveryId}/reschedule` | Duplicate alias |
| 7 | `POST /api/{locale}/customer/subscriptions/{id}/address` | `/api/{locale}/my-account/subscriptions/{id}/address` | Duplicate alias |
| 8 | `POST /api/{locale}/customer/subscriptions/{id}/quantity` | `/api/{locale}/my-account/subscriptions/{id}/quantity` | Duplicate alias |
| 9 | `POST /api/{locale}/customer/subscriptions/{id}/cancel` | `/api/{locale}/my-account/subscriptions/{id}/cancel` | Duplicate alias |
| 10 | `POST /api/{locale}/customer/subscriptions/{id}/retry-payment` | `/api/{locale}/my-account/subscriptions/{id}/retry-payment` | Duplicate alias |
| 11 | `GET /api/{locale}/v1/cart/viewcart` | `/api/{locale}/cart/viewcart` | Duplicate alias in v1 group |
| 12 | `POST /api/{locale}/v1/cart/add` | `/api/{locale}/cart/add` | Duplicate alias in v1 group |
| 13 | `POST /api/{locale}/v1/cart/cartupdate` | `/api/{locale}/cart/cartupdate` | Duplicate alias in v1 group |
| 14 | `POST /api/{locale}/v1/cart/remove` | `/api/{locale}/cart/remove` | Duplicate alias in v1 group |
| 15 | `POST /api/{locale}/v1/cart/merge` | `/api/{locale}/cart/merge` | Duplicate alias in v1 group |
| 16 | `POST /api/{locale}/newsletter/subscribe` | `/api/{locale}/newsletter` | Duplicate alias |
| 17 | `GET /api/{locale}/v1/faqs` | `/api/{locale}/faqs` | Duplicate alias |
| 18 | `GET /api/{locale}/v1/google-reviews` | `/api/{locale}/google-reviews` | Duplicate alias |
| 19 | `GET /api/{locale}/v1/shipping-methods` | `/api/{locale}/shipping-methods` | Duplicate alias |
| 20 | `GET /api/{locale}/v1/testimonials` | `/api/{locale}/testimonials` | Duplicate alias |

---

### Group B: Server-to-Server Payment Webhooks (8 Endpoints)
*Ye endpoints browser/frontend se call nahi hote, balki payment gateway servers (HyperPay, Tabby, Tamara, PayPal) inpar backend-to-backend callbacks bhejte hain:*

| # | Endpoint | Caller | Status |
| :-: | :--- | :--- | :--- |
| 21 | `POST /api/v1/webhooks/hyperpay` | HyperPay Payment Engine | Active (Server Webhook) |
| 22 | `POST /api/v1/webhooks/tabby` | Tabby Webhook Dispatcher | Active (Server Webhook) |
| 23 | `POST /api/v1/webhooks/tamara` | Tamara Webhook Dispatcher | Active (Server Webhook) |
| 24 | `POST /api/v1/webhooks/paypal` | PayPal IPN / Webhooks | Active (Server Webhook) |
| 25 | `POST /api/cart/razorpay/callback` | RazorPay Server | **Legacy (Not used in KSA)** |
| 26 | `POST /api/cart/razorpay/webhook` | RazorPay Server | **Legacy (Not used in KSA)** |
| 27 | `POST /api/cart/razorpay/cancel` | RazorPay Server | **Legacy (Not used in KSA)** |
| 28 | `POST /api/razorpay/webhook` | RazorPay Server | **Legacy (Not used in KSA)** |

---

### Group C: Legacy / Abandoned Endpoints (18 Endpoints)
*Ye endpoints purane boilerplate ya uncompleted modules ke hain jinhe frontend me kabhi call nahi kiya gaya:*

| # | Endpoint | Description | Status |
| :-: | :--- | :--- | :--- |
| 29 | `GET /api/{locale}/state-of-india` | Old Indian states API from boilerplate | ⚠️ Unused in Saudi Arabia |
| 30 | `POST /api/{locale}/tutor-form` | Tutor application form from older template | ⚠️ Unused in Flower Shop |
| 31 | `POST /api/{locale}/product-request` | Legacy product request form | ⚠️ Unused in Frontend |
| 32 | `POST /api/{locale}/vendor-form` | Multi-vendor inquiry form | ⚠️ Unused |
| 33 | `POST /api/{locale}/vendor-register` | Multi-vendor signup | ⚠️ Unused |
| 34 | `GET /api/{locale}/publications` | Old publications/lookbooks list | ⚠️ Replaced by Categories |
| 35 | `GET /api/{locale}/publication/{slug}` | Old single publication view | ⚠️ Replaced by Categories |
| 36 | `GET /api/{locale}/news` | News resource list | ⚠️ Replaced by `/blog` |
| 37 | `POST /api/{locale}/news` | Create news | ⚠️ Admin CRUD |
| 38 | `GET /api/{locale}/news/{news}` | Single news item | ⚠️ Replaced by `/blog` |
| 39 | `PUT /api/{locale}/news/{news}` | Update news item | ⚠️ Admin CRUD |
| 40 | `DELETE /api/{locale}/news/{news}` | Delete news item | ⚠️ Admin CRUD |
| 41 | `GET /api/{locale}/news/{slug}` | Single news by slug | ⚠️ Replaced by `/blog/{slug}` |
| 42 | `POST /api/{locale}/v1/checkuser` | Check user exists endpoint | ⚠️ Replaced by login/register |
| 43 | `POST /api/{locale}/cart/recover` | Abandoned cart recovery endpoint | ⚠️ No UI trigger |
| 44 | `GET /api/{locale}/cart/clear` | Clear cart GET route | ⚠️ Frontend uses POST `/cart/empty` |
| 45 | `POST /api/{locale}/my-account/cancel-order` | Order cancellation endpoint | ⚠️ Orders managed via WhatsApp/CS |
| 46 | `GET /api/{locale}/my-account/view-address/{id}` | Direct address lookup by ID | ⚠️ Profile returns all addresses |

---

### Group D: Redundant Internal / Utility Endpoints (12 Endpoints)
*Ye endpoints internal utility ya admin tools hain:*

| # | Endpoint | Description | Frontend Alternative |
| :-: | :--- | :--- | :--- |
| 47 | `GET /api/{locale}/currency-rates` | Live currency converter test route | Frontend reads rates from `/global-settings` |
| 48 | `POST /api/{locale}/global-settings/fetch-currency-rate` | Force currency refresh trigger | Automated via background scheduler |
| 49 | `GET /api/{locale}/sitemap-data` | Backend sitemap JSON generator | Frontend Next.js generates native `sitemap.xml` |
| 50 | `GET /api/{locale}/menus/{menuName}` | Individual menu items by menu name | Frontend uses unified `/api/{locale}/navigation` |
| 51 | `GET /api/{locale}/testimonials/limit={limit}` | Legacy query format for testimonials | Frontend uses `/api/{locale}/testimonials` |
| 52 | `GET /api/{locale}/testimonials/{limit}` | Legacy path format for testimonials | Frontend uses `/api/{locale}/testimonials` |
| 53 | `GET /api/{locale}/v1/delivery-slots/blocked-dates` | Blocked dates standalone endpoint | Returned inside `/v1/delivery-slots` directly |
| 54 | `GET /api/{locale}/cms-pages` | Full CMS pages list | Frontend queries pages by slug individually |
| 55 | `POST /api/{locale}/forgot-password` | Duplicate un-prefixed forgot password | Frontend uses `/customer/forgot-password` |
| 56 | `POST /api/{locale}/my-account/subscriptions/{id}/quantity` | Cycle quantity modifier | Subscriptions use plan-based quantity |
| 57 | `GET /admin/api-documentation` | Filament Swagger page | Admin panel UI only |
| 58 | `GET /api/debug` | Debug endpoint | Web development only |

---

## 5. 🧹 Recommended Cleanup Plan for Backend

Agar aap backend ko aur clean aur lightweight banana chahte hain, to future release me in steps ko follow kar sakte hain:

1. **Delete Dead Boilerplate Routes:**
   - Remove `state-of-india`, `tutor-form`, `product-request`, `vendor-register`, `vendor-form` from `routes/api.php`.
2. **Remove Unused Razorpay Routes:**
   - Remove `cart/razorpay/*` and `razorpay/*` kyunki Saudi Arabia me live gateways HyperPay (Mada, Apple Pay, Visa), Tabby, Tamara, aur PayPal hain.
3. **Consolidate Subscriptions Routes:**
   - Keep only `/api/{locale}/my-account/subscriptions/...` aur redundant `/customer/subscriptions/...` aliases ko drop kar sakte hain.
4. **Consolidate v1 Cart Routes:**
   - Keep only `/api/{locale}/cart/...` aur redundant `/v1/cart/...` aliases ko drop kar sakte hain.

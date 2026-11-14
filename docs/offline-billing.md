# Offline billing setup

## Deployment requirements

- Serve the application over HTTPS with a valid certificate. Service workers are disabled on ordinary HTTP hosts; `localhost` is the common development exception.
- Deploy the application assets, `public/service-worker.js`, `public/manifest.webmanifest`, and `public/hms-pwa-icon.svg`.
- Run the database migrations, including the offline billing request table migration.
- Use a browser that supports service workers, IndexedDB, Cache Storage, and Web Crypto.

## Preparing a browser

1. Sign in as a developer and open `/view-billing-page` while online.
2. In the developer-only offline billing panel, complete the one-time device setup. Use a new passphrase of at least 12 characters, or enter the existing offline passphrase if this developer account has older encrypted data. The non-extractable AES-GCM key is stored in IndexedDB and shared with newly used accounts in this browser; legacy plaintext drafts and requests are encrypted during setup.
3. Keep the same browser profile for offline work. Clients do not need to enter a passphrase or use a lock control. An account that already has older passphrase-encrypted data must be unlocked once with that account's old passphrase to preserve and enable silent access to its existing data.
4. Allow the billing page and its assets to finish loading once while online. Installing the PWA is optional.

The saved key and offline data are specific to this browser profile and device. Clearing browser data, using another browser/profile, or losing the device can make encrypted offline data unrecoverable. Device-key storage is not protection against a compromised device, malicious extension, or same-origin script injection. HTTPS and the normal server-side authorization checks remain necessary.

## Offline behavior and limitations

- Once prepared, a navigation to `/view-billing-page` can reopen the encrypted cached billing page without a passphrase prompt. If no usable page/key is available, the browser shows a simple setup-unavailable message instead of an unlock form.
- New bill drafts are encrypted as the form changes and restored on the same browser. Existing bill edits are not queued offline.
- Submitted offline bills and supported financial actions are stored encrypted in IndexedDB with idempotent request IDs. A provisional receipt is not a final bill.
- Unsynchronized general bills appear in the Billing List as local provisional entries. Eligible pathology samples appear in Sample Collection as waiting for synchronization; those entries are not server records and do not yet have real billing or bill-item IDs.
- The server assigns the final bill number and database IDs when synchronization succeeds. The regular Billing List and Sample Collection are refreshed afterward, and sample collection actions become available against the real server item IDs. Do not use provisional references as final report or collection IDs.
- Offline billing receipts use the online invoice layout and print automatically after saving. The offline reference appears in the Bill No field; the invoice remains provisional until synchronization. Header, footer, and institution-pad images are cached during device preparation so they can appear in offline printouts. Offline receipts also use the online A5 barcode dimensions and the configured footer content position, font size, and offset.
- In the installed app, same-origin billing navigation and saved-bill invoices stay in the app window. In a regular browser, Save & Print continues to open the invoice in a browser tab.
- Queued requests synchronize when the app is open and an authenticated browser session is available, including when connectivity returns. Background synchronization after the browser is closed is not implemented.
- Connectivity, queue counts, and sync details are hidden from ordinary clients; they are available in the developer-only billing panel. A request rejected by server validation is retained for developer review rather than treated as successfully posted.
- Inventory, prices, authorization, and other server-side rules may change while offline. Local storage is not a backup and may be cleared or evicted by the browser.

This folder contains file-based frontend templates used by the CMS.

How to use
- Put additional templates here as Blade files, e.g. `modern.blade.php`.
- From the admin `Web Setting -> CMS` you can select the desired template under "Website Template" and save.
- Each template should read settings via `get_cached_web_setting()` or the `WebSetting` model.

Portability
- Copy this entire `templates` folder to another HMS instance (same codebase) to reuse templates across subdomains or deployments.

Notes
- Keep templates simple and self-contained. They should not depend on custom controllers; use `get_cached_web_setting()` for data.
- If you need dynamic pages or additional data, create a frontend route/controller to pass extra data to the templates.

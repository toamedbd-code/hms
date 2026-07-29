<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ config('app.name') }}</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Bootstrap / CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

    @includeIf('backend.partials.header')

    <div class="container-fluid mt-3">
        @yield('content')
    </div>

    @includeIf('backend.partials.footer')

    <script>
        // Insert a Back button next to any visible Print button on the page
        document.addEventListener('DOMContentLoaded', function () {
            try {
                const candidates = Array.from(document.querySelectorAll('button, a'));
                const printBtn = candidates.find(el => {
                    const txt = (el.textContent || '').trim();
                    const onclick = el.getAttribute && el.getAttribute('onclick') || '';
                    return txt === 'Print' || txt.includes('Print') || onclick.includes('print');
                });

                if (printBtn) {
                    // avoid inserting duplicate back buttons
                    if (!document.getElementById('global-back-btn')) {
                        const back = document.createElement('button');
                        back.id = 'global-back-btn';
                        back.type = 'button';
                        back.textContent = 'Back';
                        back.style.marginRight = '8px';
                        back.className = printBtn.className || 'btn btn-sm btn-secondary';
                        back.onclick = function () { history.back(); };
                        printBtn.parentNode.insertBefore(back, printBtn);
                    }
                }
            } catch (e) {
                // ignore
            }
        });
    </script>

</body>
</html>

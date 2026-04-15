<!doctype html>
<html lang="en">
                    @php
                        $rawImg = trim((string) ($doc['image_url'] ?? ''));
                        $imgSrc = null;
                        if ($rawImg !== '') {
                            $maybe = publicStorageUrl($rawImg);
                            if ($maybe) {
                                $imgSrc = $maybe;
                            }
                        }
                        if (!$imgSrc) {
                            $imgSrc = imageNotFound();
                        }
                    @endphp
                    <div class="doctor-card">
                        <img src="{{ $imgSrc }}" alt="{{ $doc['name'] ?? '' }}" style="width:100%;height:150px;object-fit:cover;border-radius:4px;margin-bottom:8px">
                        
        <header>
            @if(!empty($setting->logo))
                <img src="{{ $setting->logo }}" alt="Logo" class="logo">
            @endif
            <div>
                <h1 style="margin:0">{{ $setting->company_name ?? 'Hospital' }}</h1>
                <div style="font-size:13px;color:#666">{{ $setting->address ?? '' }}</div>
            </div>
        </header>

        <section class="hero">
            <h2>{{ $setting->website_hero_title ?? ($setting->company_name ?? 'Welcome') }}</h2>
            <p style="color:#444">{{ $setting->website_hero_subtitle ?? '' }}</p>
            @if(!empty($setting->website_cta_text))
                <p><a href="/" style="display:inline-block;padding:8px 12px;background:#0ea5a1;color:#fff;border-radius:4px;text-decoration:none">{{ $setting->website_cta_text }}</a></p>
            @endif
        </section>

        <section class="about">
            <h3>About</h3>
            <p style="white-space:pre-wrap">{{ $setting->website_about_text ?? '' }}</p>
        </section>

        <section class="services" style="margin-top:18px">
            <h3>Services</h3>
            @php
                $serviceItems = [];
                if (!empty($setting->website_services_json)) {
                    $s = json_decode($setting->website_services_json, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($s)) $serviceItems = $s;
                }
            @endphp
            <ul>
                @foreach($serviceItems as $srv)
                    <li style="margin-bottom:6px">{{ $srv }}</li>
                @endforeach
            </ul>
        </section>

        <section class="facilities" style="margin-top:18px">
            <h3>Facilities</h3>
            @php
                $facilityItems = [];
                if (!empty($setting->website_facilities_json)) {
                    $f = json_decode($setting->website_facilities_json, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($f)) $facilityItems = $f;
                }
            @endphp
            <ul>
                @foreach($facilityItems as $fac)
                    <li style="margin-bottom:6px">{{ $fac }}</li>
                @endforeach
            </ul>
        </section>

        <section class="doctors-section" style="margin-top:18px">
            <h3>Featured Doctors</h3>
            <div class="doctors">
                @php
                    $doctors = [];
                    if (!empty($setting->website_featured_doctors_json)) {
                        $decoded = json_decode($setting->website_featured_doctors_json, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $doctors = $decoded;
                        }
                    }
                @endphp

                @forelse($doctors as $doc)
                    @php
                        $img = trim((string) ($doc['image_url'] ?? ''));
                        if ($img !== '') {
                            if (str_starts_with($img, ['http://', 'https://', 'data:'])) {
                                $imgSrc = $img;
                            } elseif (str_contains($img, 'storage/')) {
                                $imgSrc = asset($img);
                            } else {
                                $imgSrc = asset('storage/' . ltrim($img, '/'));
                            }
                        } else {
                            $imgSrc = null;
                        }
                    @endphp
                    <div class="doctor-card" style="display:flex;align-items:flex-start;gap:12px;margin-bottom:12px">
                        <div class="doctor-photo" style="width:120px;height:150px;flex:0 0 120px;overflow:hidden;background:#f7fafc;border-radius:6px;display:flex;align-items:center;justify-content:center">
                            @if($imgSrc)
                                <img src="{{ $imgSrc }}" alt="{{ $doc['name'] ?? '' }}" style="width:100%;height:100%;object-fit:cover;display:block">
                            @else
                                <div style="width:48px;height:48px;background:#eef2f7;border-radius:50%"></div>
                            @endif
                        </div>
                        <div class="doctor-info" style="flex:1;min-width:0">
                            <div style="font-weight:700">{{ $doc['name'] ?? '' }}</div>
                            <div style="font-size:13px;color:#666;white-space:pre-wrap">{{ $doc['specialty'] ?? '' }}</div>
                            <div style="font-size:13px;color:#444">{{ $doc['designation'] ?? '' }}</div>
                            <div style="font-size:12px;color:#555;margin-top:8px;white-space:pre-wrap">{{ $doc['bio'] ?? $doc['experience'] ?? '' }}</div>
                        </div>
                    </div>
                @empty
                    <div>No doctors configured.</div>
                @endforelse
            </div>
        </section>

        <footer>
            <div>Contact: {{ $setting->website_emergency_phone ?? $setting->phone ?? '' }}</div>
            <div style="margin-top:6px">&copy; {{ date('Y') }} {{ $setting->company_name ?? 'Hospital' }}</div>
        </footer>
    </div>
</body>
</html>

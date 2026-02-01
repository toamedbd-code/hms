<template>
  <span v-if="iconSvg" v-html="iconSvg" :class="classes" />
  <span v-else v-html="fallbackIcon" :class="classes" />
</template>

<script>
import feather from 'feather-icons';

export default {
  props: {
    name: {
      type: String,
      required: true,
    },
    size: {
      type: [String, Number],
      default: 24,
    },
    class: {
      type: String,
      default: '',
    },
  },
  computed: {
    classes() {
      return `icon icon-${this.name} ${this.class}`;
    },

    iconSvg() {
      // First priority: Check Feather icons
      if (feather.icons[this.name]) {
        return feather.icons[this.name].toSvg({
          width: this.size,
          height: this.size,
        });
      }

      // Second priority: Check Lucide icons (manually defined common ones)
      const lucideIcon = this.getLucideIcon(this.name);
      if (lucideIcon) {
        return lucideIcon;
      }

      return null;
    },

    fallbackIcon() {
      console.warn(`Icon "${this.name}" not found in Feather or Lucide icons.`);
      return `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10" />
        <text x="12" y="16" font-size="10" text-anchor="middle" fill="currentColor">?</text>
      </svg>`;
    }
  },
  methods: {
    getLucideIcon(iconName) {

      const lucideIcons = {
        'bed': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M2 4v16"/>
          <path d="M2 8h18a2 2 0 0 1 2 2v10"/>
          <path d="M2 17h20"/>
          <path d="M6 8V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"/>
        </svg>`,

        'plus-circle': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/>
          <path d="M8 12h8m-4-4v8"/>
        </svg>`,

        'hospital': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 6v4"/>
          <path d="M14 14h-4"/>
          <path d="M14 18h-4"/>
          <path d="M14 8h-4"/>
          <path d="M18 12h2a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-9a2 2 0 0 1 2-2h2"/>
          <path d="M18 22V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v18"/>
        </svg>`,

        'stethoscope': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.2.2 0 1 0 .3.3"/>
          <path d="M8 15v1a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6v-4"/>
          <circle cx="20" cy="10" r="2"/>
        </svg>`,

        'heart-pulse': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.29 1.51 4.04 3 5.5"/>
          <path d="m12 5-8 21 3-7h6l3 7Z"/>
        </svg>`,

        'user-check': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <polyline points="16,11 18,13 22,9"/>
        </svg>`,

        'clipboard-list': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
          <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
          <path d="M12 11h4"/>
          <path d="M12 16h4"/>
          <path d="M8 11h.01"/>
          <path d="M8 16h.01"/>
        </svg>`,

        'calendar-check': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
          <line x1="16" y1="2" x2="16" y2="6"/>
          <line x1="8" y1="2" x2="8" y2="6"/>
          <line x1="3" y1="10" x2="21" y2="10"/>
          <path d="m9 16 2 2 4-4"/>
        </svg>`,

        'role-management': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="5" r="2" />
            <line x1="12" y1="7" x2="12" y2="11" />
            <circle cx="6" cy="17" r="2" />
            <circle cx="18" cy="17" r="2" />
            <line x1="12" y1="11" x2="6" y2="15" />
            <line x1="12" y1="11" x2="18" y2="15" />
          </svg>`,


        'pills': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.5 20.5 10 21a2 2 0 0 1-2.828 0L2.343 16.172a2 2 0 0 1 0-2.828l8.486-8.486a2 2 0 0 1 2.828 0l4.829 4.829a2 2 0 0 1 0 2.828L10.5 20.5Z"/>
            <path d="m7 17-5-5"/>
            <path d="M16.5 12.5 21 8a2 2 0 0 0 0-2.828L16.172 0.343a2 2 0 0 0-2.828 0L9 4.5"/>
          </svg>`,

        'microscope': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 18h8"/>
            <path d="M3 22h18"/>
            <path d="M14 22a7 7 0 1 0 0-14h-1"/>
            <path d="M9 14h2"/>
            <path d="M9 12a2 2 0 0 1-2-2V6h6v4a2 2 0 0 1-2 2Z"/>
            <path d="M12 6V3a1 1 0 0 0-1-1H9a1 1 0 0 0-1 1v3"/>
          </svg>`,

        'activity': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="m22 12-4-4-3 3-3-3-4 4"/>
            <path d="M16 8V4h4"/>
          </svg>`,

        'flask': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="10" y="2" width="4" height="6" rx="0"/>
              <path d="M10 8L7 18c-.5 1.5.5 3 2 3h6c1.5 0 2.5-1.5 2-3L14 8"/>
              <path d="M9 2h6"/>
              <path d="M8 16h8"/>
              <path d="M9.5 14h1"/>
          </svg>`,

        'birth-death': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14,2 14,8 20,8"/>
            <circle cx="10" cy="12" r="2"/>
            <path d="M10 10c-2 0-4 1-4 4s2 4 4 4 4-1 4-4-2-4-4-4z"/>
          </svg>`,

        'human': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>`,

        'clock': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <polyline points="12,6 12,12 16,14"/>
          </svg>`,

        'calendar': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
            <line x1="16" y1="2" x2="16" y2="6"/>
            <line x1="8" y1="2" x2="8" y2="6"/>
            <line x1="3" y1="10" x2="21" y2="10"/>
          </svg>`,

        'referral': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M19 8v6"/>
            <path d="M22 11l-3-3-3 3"/>
          </svg>`,

        'umbrella': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M23 12a11.05 11.05 0 0 0-22 0zm-5 7a3 3 0 0 1-6 0v-7"/>
          </svg>`,

        'finance': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="12" rx="2"/>
            <path d="M7 15h10"/>
            <path d="M7 11h6"/>
            <circle cx="17" cy="8.5" r="1"/>
            <path d="M3 10h18"/>
          </svg>`,

        'message-square': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
          </svg>`,

        'invoice-design': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/>
            <path d="M14 2v6h6"/>
            <path d="M8 12h8"/>
            <path d="M8 16h6"/>
            <path d="M8 8h2"/>
            <circle cx="18" cy="18" r="3"/>
            <path d="M17 18h2"/>
          </svg>`,

        'pharmacy': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="4" y="6" width="16" height="14" rx="2"/>
            <path d="M4 8h16"/>
            <path d="M12 10v8"/>
            <path d="M8 14h8"/>
            <rect x="8" y="2" width="8" height="4" rx="1"/>
          </svg>`,

        'reports': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="18" height="18" rx="2"/>
            <path d="M7 8h10"/>
            <path d="M7 12h10"/>
            <path d="M7 16h6"/>
            <path d="M16 16l2-2 2 2"/>
            <path d="M18 14v4"/>
          </svg>`,

        'certificate': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="8" r="6"/>
            <path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>
            <path d="M12 6v4"/>
            <path d="M10 8h4"/>
          </svg>`,

        'layout-grid': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="7" height="7"/>
            <rect x="14" y="3" width="7" height="7"/>
            <rect x="14" y="14" width="7" height="7"/>
            <rect x="3" y="14" width="7" height="7"/>
          </svg>`,

        'video': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M23 7l-7 5 7 5V7z"/>
            <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
          </svg>`,

        'bar-chart-3': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="currentColor" stroke="none">
            <rect x="2" y="2" width="20" height="20" rx="2"/>
            <path d="M6 8v8h3V8H6zM10.5 6v10h3V6h-3zM15 10v6h3v-6h-3z" fill="white"/>
            <circle cx="7.5" cy="5" r="1" fill="white"/>
            <path d="M16 5h2M16 6h1" stroke="white" stroke-width="0.5"/>
          </svg>`,

        'settings': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/>
            <circle cx="12" cy="12" r="3"/>
          </svg>`,

        'dashboard': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
            <line x1="8" y1="21" x2="16" y2="21"/>
            <line x1="12" y1="17" x2="12" y2="21"/>
          </svg>`,

        'billing': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1Z"/>
            <path d="M8 7h8"/>
            <path d="M8 11h8"/>
            <path d="M8 15h5"/>
          </svg>`,

        'appointment': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
            <line x1="16" y1="2" x2="16" y2="6"/>
            <line x1="8" y1="2" x2="8" y2="6"/>
            <line x1="3" y1="10" x2="21" y2="10"/>
            <path d="m9 16 2 2 4-4"/>
          </svg>`,

        'opd': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.2.2 0 1 0 .3.3"/>
            <path d="M8 15v1a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6v-4"/>
            <circle cx="20" cy="10" r="2"/>
          </svg>`,

        'ipd': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="currentColor" stroke="none">
            <path d="M3 5h18a2 2 0 0 1 2 2v13H1V7a2 2 0 0 1 2-2z"/>
            <path d="M6 2h12v3H6z"/>
            <circle cx="8" cy="12" r="1" fill="white"/>
            <circle cx="16" cy="12" r="1" fill="white"/>
            <path d="M6 16h12" stroke="white" stroke-width="2"/>
          </svg>`,

        'inventory': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 6h18l-2 13H5L3 6z"/>
            <path d="M3 6L2 2H1"/>
            <circle cx="9" cy="21" r="1"/>
            <circle cx="20" cy="21" r="1"/>
            <path d="M9 12h6"/>
            <path d="M12 9v6"/>
            <rect x="7" y="7" width="10" height="8" rx="1"/>
          </svg>`,

        'pathology': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10 2h4"/>
            <path d="M10 2v6l-6 12h16l-6-12V2"/>
            <path d="M4 20h16"/>
            <path d="M7 16h10"/>
          </svg>`,

        'radiology': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 1v6"/>
            <path d="M12 17v6"/>
            <path d="M3.515 4.929l4.243 4.243"/>
            <path d="M16.242 16.242l4.243 4.243"/>
            <path d="M1 12h6"/>
            <path d="M17 12h6"/>
            <path d="M4.929 20.485l4.243-4.243"/>
            <path d="M16.242 7.758l4.243-4.243"/>
            <circle cx="12" cy="12" r="3"/>
          </svg>`,

        'blood-bank': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="currentColor" stroke="none">
          <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/>
        </svg>`,

        'ambulance': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="currentColor" stroke="none">
          <path d="M2 5h12v12H5a3 3 0 1 1 0-6h10V5H2z"/>
          <path d="M22 12h-4v5h2a3 3 0 1 1 0-6h2v1z"/>
          <circle cx="7" cy="17" r="2" fill="white"/>
          <circle cx="17" cy="17" r="2" fill="white"/>
          <path d="M8 12h2M9 11v2" stroke="white" stroke-width="2" stroke-linecap="round"/>
        </svg>`,

        'bloodComponent': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="currentColor" stroke="none">
          <path d="M8 3h8c1.1 0 2 .9 2 2v14c0 1.1-.9 2-2 2H8c-1.1 0-2-.9-2-2V5c0-1.1.9-2 2-2z"/>
          <rect x="10" y="2" width="4" height="2" fill="white"/>
          <path d="M14 8h4v4h-4z"/>
          <circle cx="16" cy="10" r="1" fill="white"/>
          <path d="M9 14h6M9 16h6" stroke="white" stroke-width="1"/>
          <path d="M12 7v2M11 8h2" stroke="white" stroke-width="1" stroke-linecap="round"/>
        </svg>`,

        'payroll': `<svg width="${this.size}" height="${this.size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="2" y="4" width="20" height="16" rx="2"/>
          <path d="M8 8h8"/>
          <path d="M8 12h8"/>
          <path d="M8 16h5"/>
          <path d="M10 8H8v4h2V8z"/>
        </svg>`
      };

      return lucideIcons[iconName] || null;
    }
  }
};
</script>

<style scoped>
.icon {
  display: inline-block;
  vertical-align: middle;
}

.icon svg {
  display: block;
}
</style>
import { defineExtension } from "@tracht-digital-solutions/tds-panel-contract";

/**
 * Customer/company directory manifest — the panel's canonical customer list,
 * backing membership editing (base user-management) and the billing/portal
 * extensions. No settings slot (no config). Admin-facing.
 */
export default defineExtension({
  id: "customers",
  name: "Kunden",
  version: "0.1.0",
  permissions: [
    { id: "customers:read", label: "Kunden ansehen", group: "customers" },
    { id: "customers:write", label: "Kunden verwalten", group: "customers" },
  ],
  nav: [
    {
      id: "customers",
      label: "Kunden",
      href: "/customers",
      icon: "users",
      group: "verwaltung",
      order: 15,
      permission: "customers:read",
    },
  ],
  widgets: [
    {
      id: "customers-count",
      title: "Kunden",
      island: "@tracht-digital-solutions/tds-ext-customers/widgets/Widget.astro",
      size: "sm",
      permission: "customers:read",
      dataEndpoint: "/customers/summary",
      order: 15,
    },
  ],
  routes: [
    {
      pattern: "/customers",
      entrypoint: "@tracht-digital-solutions/tds-ext-customers/pages/Index.astro",
      permission: "customers:read",
    },
  ],
  i18n: {
    de: { "customers.title": "Kunden" },
    en: { "customers.title": "Customers" },
  },
});

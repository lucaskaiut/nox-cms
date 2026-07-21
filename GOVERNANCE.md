# Template Governance — nox-skeleton

> The template is a **generic multi-tenant SaaS starter** — not a CMS, ERP, or eCommerce.

**Location:** `../nox-skeleton` (filesystem sibling of the project directory)

---

## Repository Layout

```
development/
├── nox-skeleton/       ← Template (CORE only)
└── nox-cms/            ← This project (CORE + PROJECT)
```

The template lives as a **sibling directory** on disk, not as a git remote.
Each repository has its own `origin` remote — no cross-repo merging.

---

## Template = Generic Infrastructure

The template provides what **any SaaS** needs: multi-tenancy, ACL, auth, user/role/token management, Design System, layouts, guards, Docker.

**Project** code is domain-specific: posts, products, invoices, leads, AI publishers.

---

## CORE (Template)

| Layer | Concern |
|---|---|
| **Backend** | Tenant resolver, ACL (RBAC), Auth (Sanctum), User/Token CRUD, Shared utils, File upload, Middleware, Error handling, DB structure for core entities |
| **Frontend** | Design System components, HTTP layer, Stores, Guards, Layouts, Router, Providers, Auth/Dashboard/Users/Roles/Tokens modules |
| **Infra** | Docker Compose, PHP/Nginx/MySQL/Node containers |

## PROJECT (Application)

| Concern |
|---|
| Blog / Posts / Categories |
| Products / Orders / eCommerce |
| Invoices / ERP |
| CRM entities |
| AI content publisher |
| Any domain-specific entity or workflow |

---

## Workflow

### Changing PROJECT code

Work normally in this directory. Commit to `origin`.

### Changing CORE code

1. Implement the change in this project directory (tests, integration).
2. After the change works, copy the affected CORE files to `../nox-skeleton`.
3. The filesystem paths are identical between both repos — use `rsync` or `cp`:

```
rsync -av --relative api/app/Modules/Shared/  ../nox-skeleton/
```

4. Commit in this repo; commit separately in `../nox-skeleton`.

### Changing HYBRID code

Implement the generic part in both repos. Keep the domain-specific part only in this project.

---

## Key Rules

- **Permission enum** in template contains only infrastructure permissions (user.*, tenant.*, role.*, api-token.*, webhook.*). Domain permissions go in the project.
- **Design System components** are always CORE — UI primitives, not domain logic.
- **Decision test:** "Would an ERP, eCommerce, or CRM need this?" No → PROJECT.
- **Never copy project files into the template.** The template must stay domain-free.

---

## Closing Checklist

- [ ] Change classified (CORE / PROJECT / HYBRID)?
- [ ] If CORE: files copied to `../nox-skeleton`?
- [ ] If PROJECT: no template files touched?
- [ ] Both repos committed?

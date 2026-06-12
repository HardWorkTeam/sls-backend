# Srolanh Wedding Management Platform — ERD (v1)

```mermaid
erDiagram
  USERS ||--o{ WEDDING_MEMBERS : has
  WEDDINGS ||--o{ WEDDING_MEMBERS : has
  PACKAGES ||--o{ WEDDINGS : used_by

  WEDDINGS ||--o{ INVITATIONS : has
  INVITATION_TEMPLATES ||--o{ INVITATIONS : uses

  WEDDINGS ||--o{ GUEST_GROUPS : has
  WEDDINGS ||--o{ GUESTS : has
  GUEST_GROUPS ||--o{ GUESTS : groups
  INVITATIONS ||--o{ GUESTS : invited_via

  WEDDINGS ||--o{ RSVP_RESPONSES : has
  INVITATIONS ||--o{ RSVP_RESPONSES : collects
  GUESTS ||--o{ RSVP_RESPONSES : responds

  WEDDINGS ||--o{ WEDDING_TABLES : has
  WEDDING_TABLES ||--o{ GUEST_SEATINGS : seats
  GUESTS ||--o| GUEST_SEATINGS : assigned

  WEDDINGS ||--o{ GIFTS : has
  GUESTS ||--o{ GIFTS : gives

  WEDDINGS ||--o{ TIMELINE_EVENTS : has

  WEDDINGS ||--o{ ALBUMS : has
  ALBUMS ||--o{ MEDIA_ITEMS : contains
  USERS ||--o{ MEDIA_ITEMS : uploads

  WEDDINGS ||--o{ ANNOUNCEMENTS : has
  ANNOUNCEMENTS ||--o{ NOTIFICATION_LOGS : logs
  USERS ||--o{ NOTIFICATION_LOGS : receives
  GUESTS ||--o{ NOTIFICATION_LOGS : receives

  USERS ||--o{ ROLE_USER : has
  ROLES ||--o{ ROLE_USER : assigned
  ROLES ||--o{ PERMISSION_ROLE : has
  PERMISSIONS ||--o{ PERMISSION_ROLE : granted
```

## Table Inventory (Planned)

### Auth & RBAC
- `users` (existing)
- `roles`
- `permissions`
- `role_user`
- `permission_role`

### Core Wedding
- `packages`
- `weddings`
- `wedding_members`

### Digital Invitation
- `invitation_templates`
- `invitations`

### Guests & RSVP
- `guest_groups`
- `guests`
- `rsvp_responses`

### Seating
- `wedding_tables`
- `guest_seatings`

### Gifts
- `gifts`

### Timeline
- `timeline_events`

### Gallery
- `albums`
- `media_items`

### Announcements
- `announcements`
- `notification_logs`


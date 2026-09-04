# Rework Parity Catalog

This catalog records observable Rework behavior for Dexterton WorkHub. It is based on authorized read-only inspection and should be expanded before each module is accepted.

## Account And Administration

Observed screens:

- My Account profile, security, sessions, connected application access, preferences.
- Users, guests, user groups, general company information, offices, admin roles, customizations, system settings.

Implemented now:

- Users, offices, groups, roles, permissions, seeded Dexterton organization, application grants by permission-aware navigation.
- Audit trail table and admin audit viewer.
- Local login with status checks and immutable login/logout audit events.

Open parity gaps:

- Invitation lifecycle, password reset UI, TOTP setup screen, recovery codes, device/session revocation, emergency administrator workflow, branding editor, guest account workflow.

## Work Operations

Observed modules:

- Service Flows, Requests, Workflows, Expenses, Projects, Bookings, Offices.

Implemented now:

- Service flow definitions with intake schema, status pipeline, SLA fields, version marker, and published state.
- Requests with drafts, submission, approval/rejection/cancellation transitions, approval steps, activity/audit history, priority, assignment, SLA due date, and form data.
- Workflows with draft/published definitions, versioned trigger/node/edge JSON, manual run creation, execution history.
- Projects with progress, tasks, assignees, board lanes, priorities, dates, and descriptions.

Open parity gaps:

- Visual workflow builder editing, timer nodes, escalation jobs, parallel approval quorum rules, expenses, bookings, office administration workflows, exports, advanced filters.

## Collaboration And Communication

Observed modules:

- Messages, townhall, meetings, work rules, docs, collaborative documents, mail services.

Implemented now:

- Schema primitives for comments, attachments, reminders, notifications through outbox, and private file storage configuration.

Open parity gaps:

- Real-time presence, chat threads, townhall posts, meeting minutes, collaborative document provider, autosave, document version diff, mail adapters.

## Platform Services

Observed modules:

- E-Sign, automations, webforms, datasets, my files, launcher, account, connect, bots, API/security administration.

Implemented now:

- `/api/v1` resource catalog, outbox table, migration batches and external-ID mappings, file metadata table, application launcher shell.

Open parity gaps:

- E-signature evidence package, signed PDF generation, webhooks, webform public intake, dataset UI, integrations, bot runtime, security center.

## Acceptance Rule

A phase is accepted only when the product owner confirms the observable screens, fields, actions, states, permissions, filters, exports, notifications, edge cases, and migration results have no unresolved critical differences.

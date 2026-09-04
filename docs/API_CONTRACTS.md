# API Contracts

All API routes live under `/api/v1`. Browser and API operations should share application services as the implementation grows so permissions and state transitions stay identical.

## Conventions

- UUID primary keys.
- JSON responses.
- Cursor pagination will be added for large collections; current pilot endpoints use Laravel pagination.
- Validation errors follow Laravel's standard `message` plus `errors` object.
- Webhook deliveries must use idempotency keys and signed payloads when implemented.

## Current Resources

- `GET /api/v1` returns the resource catalog.
- `GET /api/v1/users` returns users with office, roles, and groups.
- `GET /api/v1/groups` returns groups with user counts.
- `GET /api/v1/projects` returns projects with task counts.
- `GET /api/v1/requests` returns work requests with approval steps.
- `GET /api/v1/workflows` returns workflow definitions with published versions.
- `GET /api/v1/todos` returns todos.
- `GET /api/v1/audit` returns audit events.

## Stable Domains

- Users and groups.
- Files and attachments.
- Comments and activity.
- Notifications and reminders.
- Workflow definitions, versions, and runs.
- Requests and approval steps.
- Projects, tasks, and todos.
- Audit events and migration mappings.

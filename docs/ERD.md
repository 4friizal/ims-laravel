# Entity Relationship Diagram — IMS Restaurant

Derived from `database/migrations/` and `app/Models/`.

## Diagram

```mermaid
erDiagram
    USERS {
        bigint id PK
        string name
        string email UK
        timestamp email_verified_at "nullable"
        string password
        string role "enum: admin | operator"
        string remember_token "nullable"
        timestamp created_at
        timestamp updated_at
    }

    CATEGORIES {
        bigint id PK
        string name
        string description "nullable"
        timestamp created_at
        timestamp updated_at
    }

    RAW_MATERIALS {
        bigint id PK
        bigint category_id FK "cascade on delete"
        string name
        string unit "enum: kg | gram | liter | ml | pcs"
        decimal minimum_stock "12,2"
        timestamp created_at
        timestamp updated_at
    }

    MENUS {
        bigint id PK
        string name
        string description "nullable"
        decimal price "12,2"
        boolean is_active "default true"
        timestamp created_at
        timestamp updated_at
    }

    MENU_MATERIALS {
        bigint id PK
        bigint menu_id FK "cascade on delete"
        bigint raw_material_id FK "cascade on delete"
        decimal quantity "12,2 - recipe portion"
        timestamp created_at
        timestamp updated_at
    }

    STOCK_BATCHES {
        bigint id PK
        bigint raw_material_id FK "cascade on delete"
        decimal quantity "12,2 - received amount"
        decimal remaining_quantity "12,2 - FIFO leftover"
        decimal unit_cost "12,2"
        date received_at
        string note "nullable"
        timestamp created_at
        timestamp updated_at
    }

    STOCK_MOVEMENTS {
        bigint id PK
        bigint raw_material_id FK "cascade on delete"
        bigint menu_id FK "nullable, null on delete"
        bigint stock_batch_id FK "nullable, null on delete"
        string type "enum: in | out"
        decimal quantity "12,2"
        decimal unit_cost "12,2 nullable"
        decimal total_cost "12,2"
        string reference "nullable"
        datetime occurred_at
        timestamp created_at
        timestamp updated_at
    }

    STOCK_MOVEMENT_BATCH {
        bigint id PK
        bigint stock_movement_id FK "cascade on delete"
        bigint stock_batch_id FK "cascade on delete"
        decimal quantity_consumed "12,2"
        decimal unit_cost "12,2"
        timestamp created_at
        timestamp updated_at
    }

    MATERIAL_USAGES {
        bigint id PK
        bigint raw_material_id FK "cascade on delete"
        date usage_date
        decimal quantity "12,2 - daily aggregate"
        timestamp created_at
        timestamp updated_at
    }

    CATEGORIES     ||--o{ RAW_MATERIALS        : "classifies"
    RAW_MATERIALS  ||--o{ MENU_MATERIALS       : "used in recipes"
    MENUS          ||--o{ MENU_MATERIALS       : "has recipe lines"
    RAW_MATERIALS  ||--o{ STOCK_BATCHES        : "stocked as"
    RAW_MATERIALS  ||--o{ STOCK_MOVEMENTS      : "moved by"
    RAW_MATERIALS  ||--o{ MATERIAL_USAGES      : "consumed daily"
    MENUS          |o--o{ STOCK_MOVEMENTS      : "triggers (optional)"
    STOCK_BATCHES  |o--o{ STOCK_MOVEMENTS      : "direct batch ref (optional)"
    STOCK_MOVEMENTS ||--o{ STOCK_MOVEMENT_BATCH : "FIFO breakdown"
    STOCK_BATCHES  ||--o{ STOCK_MOVEMENT_BATCH : "drawn from"
```

## Relationship Notes

| Relationship | Type | Notes |
|---|---|---|
| `categories` → `raw_materials` | 1:N | `Category::rawMaterials()` |
| `menus` ↔ `raw_materials` | M:N via `menu_materials` | Pivot carries `quantity` (recipe portion). Unique on `(menu_id, raw_material_id)` |
| `raw_materials` → `stock_batches` | 1:N | FIFO batches; `remaining_quantity` tracks leftover. `StockBatch::scopeAvailable()` orders by `received_at, id` |
| `raw_materials` → `stock_movements` | 1:N | Every in/out transaction |
| `menus` → `stock_movements` | 1:N (optional) | Set when an OUT movement comes from selling a menu item; `nullOnDelete` |
| `stock_batches` → `stock_movements` | 1:N (optional) | Denormalized direct reference; `nullOnDelete` |
| `stock_movements` ↔ `stock_batches` | M:N via `stock_movement_batch` | The real FIFO breakdown — one OUT movement can drain several batches. Pivot carries `quantity_consumed` and `unit_cost` |
| `raw_materials` → `material_usages` | 1:N | Daily aggregate used for stock prediction. Unique on `(raw_material_id, usage_date)` |

`users` stands alone — it is not referenced by any inventory foreign key. Access is enforced at the route level through `RoleMiddleware` and the `Role` enum, not through ownership columns.

## Excluded Tables

Laravel framework tables, not part of the domain model: `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`.

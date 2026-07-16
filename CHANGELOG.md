# Change Log

### Version 0.0.2 (Alpha 2)
Release --

- ADDED: Ajax parent-entry search picker on the entry Hierarchy tab, replacing the placeholder select (#45)
- ADDED: `doctor` Command for system health (#66)
- ADDED: Additional Unit and Feature tests (#36)
- ADDED: Super Admin bypass log (#30)
- FIXED: Where some Entry Tree request fields were validated but silently ignored on create/update (#44)
- FIXED: `EntryQueryBuilder::where()` collapses an explicit `null` third argument (#61)
- FIXED: Seeders and Field Layouts so Field Groups were aligned (#42)
- FIXED: BotBlockRequest ignores PUT, PATCH, DELETE (#21)
- FIXED: Error message on user CRUD when role isn't select (#69)
- FIXED: Badly formatted templates causing issues (#39)
- FIXED: Entry fillable exposes `created_by_user_id` (#22)
- FIXED: BotBlock Needs Session Storage of Field Name (#23)
- FIXED: Account API routes (#17)
- FIXED: Entries API never verifying entry group exists (#54)
- FIXED: Personal access token flashed in URL session flash string (#15)
- UPDATED: Moved `app` logic to stand-alone composer package (#12)
- UPDATED: Indexes to `field_values` table for query performance (#65)
- UPDATED: Tab handle validation to use uniqueness by layout (#37)
- UPDATED: Api\v1\User::update() to enforce the permission check (#18)
- UPDATED: Split EntryService tree logic into EntryTreeService (#58)

### Version 0.0.1 (Alpha 1)
Release June 29, 2026

- Initial Release

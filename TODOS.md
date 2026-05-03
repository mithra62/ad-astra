1. -- Genearte claude.md file for project
2. ensure seeder generates podcast field group for testing (ensure "episode_number" is created as number field)
3. Prevent changing field if data exists for said field
4. -- Implement custom field validation rules on entries and categories
5. Convert singleton DI objects to Facades (AppServiceProvider)
6. Get Relationship Field working
7. Generate instructions on how to implement a new Model based field/value layer ala Entries and Categories (and Users, if makes sense)
8. -- Update Repository objects to extend from a singular base object and/or define their implementation logic. Consistency is important here. All should "look" and "feel" identical
9. Add toggle to Entry Group to allow/deny Author field on crud forms
10. Automatically create and link Layout Groups when Entry and Category Groups are created
11. Move User Layout ID logic form singleton table to settings db (create seeder)
12. Remove mithra62/Shop from composer
13. Enforce a rule that changing a status handle can only happen if no entries are assigned
14. Create Field Layout for Category Group upon Category Group creation
15. Super Admin Gate Bypass Has No Audit Trail
16. api_logs Captures Full Response Bodies for All JSON Responses
17. Finish up Custom Field layer and generate more internal fields like select and multiselect
18. Implement soft deletes for Entries and Categories
19. Implement soft deletes for Users
20. Implement soft deletes for Statuses

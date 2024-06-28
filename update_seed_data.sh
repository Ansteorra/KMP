#! /bin/sh.

cd ./app
bin/cake bake seed --data app_settings
bin/cake bake seed --data branches
bin/cake bake seed --data member_roles
bin/cake bake seed --data members
bin/cake bake seed --data roles_permissions
bin/cake bake seed --data roles
bin/cake bake seed --data permissions
bin/cake bake seed --data activities_activity_groups
bin/cake bake seed --data activities_activities
bin/cake bake seed --data officers_departments
bin/cake bake seed --data officers_offices
bin/cake bake seed --data awards_domains
bin/cake bake seed --data awards_levels
bin/cake bake seed --data awards_awards

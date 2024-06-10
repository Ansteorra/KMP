#! /bin/sh.

cd ./app
bin/cake bake seed --data AppSettings
bin/cake bake seed --data Branches
bin/cake bake seed --data MemberRoles
bin/cake bake seed --data Members
bin/cake bake seed --data RolesPermissions
bin/cake bake seed --data Roles
bin/cake bake seed --data Permissions
bin/cake bake seed --data ActivityGroups
bin/cake bake seed --data Permissions
bin/cake bake seed --data Activities
bin/cake bake seed --data Departments
bin/cake bake seed --data Offices
bin/cake bake seed --data Officers
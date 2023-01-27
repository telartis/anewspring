# PHP client implementation for aNewSpring's API

This API client makes it possible to manage users, user groups, single sign-on (sso) access codes, courses, course subscriptions and instance management.

For more info see: https://support.anewspring.com/en/articles/70410-api-introduction

## It is not possible to use the aNewSpring API to:

- delete a role: `deleteUserRoles`
- delete the date of birth: `updateUser => dateOfBirth`
- see which groups a user is a member of: `getUser => groups`
- request information from a group: `getGroup` and `getGroups`
- quickly update all facilitator/participant relationships: `getTeacherStudents`
- request permissions of a course: `getCoursePermissions`

# PHP client implementation for aNewSpring's API

This PHP client makes it possible to manage users, user groups, single sign-on (sso) access codes, courses, course subscriptions and instance management.

For more info see: https://support.anewspring.com/en/articles/70410-api-introduction

## It is not possible to use the aNewSpring API to:

1. delete a role: [`deleteUserRoles`](https://github.com/telartis/anewspring/blob/main/anewspring.php#L667)
2. delete the date of birth: [`updateUser => dateOfBirth`](https://github.com/telartis/anewspring/blob/main/anewspring.php#L597)
3. see which groups a user is a member of: [`getUser => groups`](https://github.com/telartis/anewspring/blob/main/anewspring.php#L520)
4. request information from a group: [`getGroup`](https://github.com/telartis/anewspring/blob/main/anewspring.php#L708) and [`getGroups`](https://github.com/telartis/anewspring/blob/main/anewspring.php#L721)
5. quickly update all facilitator/participant relationships: [`getTeacherStudents`](https://github.com/telartis/anewspring/blob/main/anewspring.php#L1055)
6. request permissions of a course: [`getCoursePermissions`](https://github.com/telartis/anewspring/blob/main/anewspring.php#L1075)

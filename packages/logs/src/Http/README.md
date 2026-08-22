# HTTP adapter

`LogController` is transport-neutral. Map its methods to your router or framework as needed.

Suggested routes:

```text
POST /api/v1/logs
GET  /api/v1/logs
GET  /api/v1/logs/{id}
GET  /api/v1/logs/subject/{type}/{id}
GET  /api/v1/logs/actor/{id}
```

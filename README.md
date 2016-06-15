gapper-auth-gateway
===================
通过Campus Gateway API 进行认证
### 通过 http://gateway.xxx 进行用户账号的验证和用户信息的获取

1. app.yml的配置
```
---
rpc:
  gateway:
    url: http://gateway.xxx/api
    client_id: <YOURCLIENTID>
    client_secret: <YOURCLIENTSECRET>
...
```

2. gapper.yml的配置
```
---
auth:
  gateway:
    icon: assets/img/gateway.png
    client_id: <YOURCLIENTID>
    client_secret: <YOURCLIENTSECRET>
...
```


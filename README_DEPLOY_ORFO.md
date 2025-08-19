

## Git & CI (коротко)
1) Разместите **боевой** `api/config.php` только на сервере (локально можно иметь, но он в .gitignore и не попадёт в git).
2) На GitHub в Settings → Secrets → Actions добавьте:
   - SSH_HOST = buyanoff.com
   - SSH_USER = u3232651
   - SSH_KEY  = приватный ключ (см. ниже)
   - DEPLOY_PATH = /var/www/u3232651/data/www/orfo.club

### Как получить SSH_KEY (для GitHub Actions)
Вариант А — **использовать уже созданный**, если он работает:
- Приватный ключ обычно лежит на Mac в `~/.ssh/ИД_КЛЮЧА` (например, `~/.ssh/buyanoff`).
- Убедитесь, что его публичная часть (`~/.ssh/ИД_КЛЮЧА.pub`) добавлена на сервер в `~/.ssh/authorized_keys` пользователя `u3232651`.
- Проверьте вход: `ssh -i ~/.ssh/ИД_КЛЮЧА u3232651@buyanoff.com`.
- Содержимое приватного ключа вставьте в Secret `SSH_KEY` целиком (от `-----BEGIN OPENSSH PRIVATE KEY-----` до `-----END...-----`).

Вариант Б — **создать новый ключ** специально для деплоя:
```
ssh-keygen -t ed25519 -C "deploy@orfo.club" -f ~/.ssh/orfo_deploy -N ""
cat ~/.ssh/orfo_deploy.pub
```
- Выведите `.pub` и добавьте его в `~/.ssh/authorized_keys` на сервере (через Shell-клиент ISPmanager).
- Проверьте вход: `ssh -i ~/.ssh/orfo_deploy u3232651@buyanoff.com`.
- Приватный `~/.ssh/orfo_deploy` вставьте в Secret `SSH_KEY` на GitHub.

> Для CI лучше ключ **без** пароля (passphrase), чтобы Actions не запрашивал его.

### Вернутся ли секреты после заливки «чистого» git?
- `.gitignore` **не позволит** добавить `api/config.php` в репозиторий при обычном `git add .`.
- Вы можете держать `api/config.php` локально (для разработки) и на сервере — он будет игнорироваться.
- Если вы **когда-то** случайно закоммитили секрет, он останется в истории. В этом случае:
  1) удалите его из индекса: `git rm --cached api/config.php && git commit -m "remove secret"`
  2) поменяйте пароли/ключи (ротация)
  3) при необходимости очистите историю (`git filter-repo`), но если вы создаёте **новый чистый репо**, это не нужно.

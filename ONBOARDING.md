# ONBOARDING — akinams053/seat-fitting

交接快照日期：2026-05-20。当前本地仓库处于 **dirty** 状态：最新提交是 `6819e7a`（tag `1.1.1`），但本轮 UI/技能要求编辑/军团昵称列改造尚未 commit、push、tag。

这份文档给下一位接手者快速建立上下文；更底层的架构和命令约定见 `CLAUDE.md`。

---

## 1. 项目定位

`akinams053/seat-fitting` 是 SeAT 5.x 插件，不是独立 Laravel 应用。它通过 `CryptaTech\Seat\Fitting\FittingServiceProvider` 注册到宿主 SeAT。

当前业务语义：

- **配装**：单套具体 EFT 配置。
- **配装分组**：底层仍使用旧 `Doctrine` 表，但 UI 上必须叫“配装分组”，作用是把多套具体配装放进一个组。
- **个人配装检查**：普通成员检查自己的角色是否满足单配装或配装分组要求。
- **配装录入**：管理员维护配装和最低/进阶技能要求。
- **军团技能检查**：管理者按配装分组、联盟/公司维度批量检查成员技能。
- **舰队审查**：目前只是未来方向，尚未实现。

不要把 namespace、route name、DB 前缀改成 `akinams053`：为兼容上游安装，它们仍然保留 `CryptaTech\Seat\Fitting\`、`cryptafitting::*`、`crypta_tech_seat_*`。

---

## 2. 当前 Git / 发布状态

| 项目 | 状态 |
|---|---|
| 当前分支 | `master` |
| 最新提交 | `6819e7a fix: reduce corporation report skill loading` |
| 当前版本标签 | `1.1.1` |
| 本地工作区 | dirty，17 个文件未提交 |
| 测试服 | 已用这些 dirty 文件直接热更新 vendor 目录 |
| 生产服 | 本轮未动，任何生产写操作必须再次确认 |
| Packagist | 当前正式版本仍是 `1.1.1`；本轮改动未上 Packagist |

当前未提交改动覆盖：

- `FittingController.php`
- `routes.php`
- `CharacterSkillSnapshotService.php`
- `CorporationSkillReportService.php`
- `PersonalSkillCheckService.php`
- `src/lang/{en,fr,zh-CN}/{doctrine,fitting}.php`
- `fitting.js`
- `fitting-jquery.js`
- `doctrinereport.blade.php`
- `fitting.blade.php`
- `includes/display-fit.blade.php`
- `includes/display-skills.blade.php`

交接后如果测试服 UI 确认没问题，建议下一步：

```bash
git diff --check
node --check src/resources/assets/js/fitting.js
node --check src/resources/assets/js/fitting-jquery.js
# 本机没有 php；PHP lint 需在测试服/宿主 SeAT 跑

git add <上述改动文件>
git commit -m "feat: improve fitting skill checks"
git tag 1.1.2
git push origin master
git push origin 1.1.2
```

提交前不要加入 `.creds`、`.creds.test`、`scripts/test.key`、任何 `*.key` / `*.pem`。

---

## 3. 测试服状态

测试服通过 `scripts/ssh-seat -t test 'cmd'` 操作。

| 项目 | 值 |
|---|---|
| SeAT 路径 | `/var/www/seat` |
| 插件路径 | `/var/www/seat/vendor/akinams053/seat-fitting` |
| Web URL | `http://ylxh.de` |
| Laravel | `10.50.2` |
| PHP | `8.4.18` |
| Composer | `2.9.8` |
| APP_ENV | `local` |
| APP_DEBUG | enabled |
| composer show | 仍显示 `akinams053/seat-fitting 1.1.1` |

重要：测试服 composer 仍认为安装的是 `1.1.1`，但 vendor 里的插件文件已通过 SFTP 直接同步了本地 dirty 改动。这是临时测试状态，不是正式发布状态。

本轮同步后执行过：

```bash
cd /var/www/seat
find vendor/akinams053/seat-fitting/src -name "*.php" -print0 | xargs -0 -n1 php -l
php artisan vendor:publish --force --provider="CryptaTech\\Seat\\Fitting\\FittingServiceProvider"
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

---

## 4. 本轮已完成的功能改造

### 4.1 个人配装检查布局

用户明确纠正过布局：**具体配装详情应放在左侧“个人配装检查/配装录入”卡片下方，不是右侧技能检查下方。** 当前已按这个要求调整。

当前布局：

```text
左侧：个人配装检查 / 配装录入
  - 检查方式：单配装 / 配装分组
  - 配装列表与搜索
  - 具体配装详情（按槽位折叠）
  - 管理模式下 EFT 导出/编辑弹窗入口

右侧：技能检查
  - 角色选择
  - 最低要求 / 进阶要求两列
  - 技能按分类折叠
```

单配装普通检查不再把 EFT 文本作为主内容展示。配装详情包括：高槽、中槽、低槽、改装件、子系统、无人机舱、货柜。

### 4.2 技能检查展示

- 最低要求和进阶要求分成两列。
- 技能按分类折叠。
- 有缺口的分类默认展开；已满足的分类默认折叠。
- 技能等级用 5 格分段条展示。
- 达标/超过为绿色；未达标缺口为红色；要求等级用黑色 marker 表示。

### 4.3 技能要求编辑器

管理页 `配装录入` 中，最低要求和进阶要求都支持：

- 新增技能
- 删除技能
- 修改等级
- 保存完整列表

添加技能不再手填 typeID，而是：

```text
技能分类下拉 → 技能搜索下拉 → 等级 → 添加
```

新增接口：

- `GET /fitting/skill-groups`
- `GET /fitting/skills/search?group_id=&q=`

保存要求时后端会校验：只能保存 EVE SDE 中 `categoryID = InvGroup::SKILL_CATEGORY_ID` 且 `published = true` 的技能 type。这样可以避免误把舰船/装备或 `Fake Skills` 加进要求。

### 4.4 技能名翻译决策

用户反馈“机翻很难看”。测试服确认：

- `invTypes` 只有英文 `typeName` 字段。
- 没有 `trn*` 或 `translation*` 官方本地化表。

因此当前策略是：**技能名和技能分类使用 EVE SDE 官方英文名，不做机翻。**

中文界面只翻译插件自己的 UI 文案，不翻译技能名。

### 4.5 军团技能检查

已保留 1.1.1 的内存优化：军团检查先汇总所需技能，再只加载这些技能，而不是加载所有角色所有技能。

新增“昵称”列：

- 来源：`character_infos.title`
- 示例：`Snow Country` 的 `title` 是 `雪国`
- 军团技能检查 JSON 中 `charsById[*].title` 会返回该值。
- 表格列顺序：角色 / 昵称 / 各配装检查结果。

“进阶未配置”文案已明确化：

- zh-CN：`进阶未配置`
- en：`Advanced Not Set`
- fr：`Avancé non configuré`

---

## 5. 已验证结果

本地可用检查已通过：

```bash
node --check src/resources/assets/js/fitting.js
node --check src/resources/assets/js/fitting-jquery.js
git diff --check
翻译 key 对齐检查
```

测试服已通过：

```bash
php -l  # 插件 src 下全部 PHP/Blade 文件
php artisan route:list | grep fitting
php artisan vendor:publish --force --provider="CryptaTech\\Seat\\Fitting\\FittingServiceProvider"
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

后端 smoke test：

- 技能分类接口正常，不再返回 `Fake Skills`。
- 技能搜索接口正常，例如 Spaceship Command / Frigate 返回官方技能名。
- 个人单配装服务层可执行。
- 配装组服务层可执行。
- 军团技能检查全公司数据可执行：

```json
{
  "doctrine": 2,
  "fittings": 1,
  "chars": 464,
  "seconds": 0.507,
  "peak_mb": 60.5,
  "snow": {
    "name": "Snow Country",
    "title": "雪国"
  }
}
```

已查看测试服相关 Laravel 日志，没有发现新的 `seat-fitting / runReport / ViewException / QueryException` 报错。

---

## 6. 下一位接手者第一优先级

必须先由人打开测试服浏览器点 UI。Claude Code 当前无法实际看浏览器。

检查路径：

1. `个人配装检查`
   - 单配装模式：选一套配装。
   - 左侧下方应显示具体配装详情，不应出现在右侧技能检查下方。
   - 普通检查主流程不应突出 EFT 文本。
   - 技能检查应是最低/进阶两列、分类折叠、5 格进度条。
2. `个人配装检查` → 配装组模式
   - 选择配装分组。
   - 每套配装应单独显示技能检查结果。
3. `配装录入`
   - 最低要求可新增、删除、改等级、保存。
   - 进阶要求可新增、删除、改等级、保存。
   - 添加技能通过技能分类和技能搜索完成。
   - 技能下拉应显示官方英文名，不应有 `Fake Skills`。
4. `军团技能检查`
   - 表格应显示“角色 / 昵称 / 配装结果”。
   - Snow Country 的昵称应显示“雪国”。
   - 灰色 badge 应显示“进阶未配置”。
   - 大范围公司检查不应超时。

如果浏览器验证通过，再正式 commit / tag / push。

---

## 7. 标准测试服部署方式

正式 release 后建议让测试服走 composer，而不是继续 SFTP 热改 vendor：

```bash
scripts/ssh-seat -t test 'cd /var/www/seat && \
  php artisan down && \
  composer update akinams053/seat-fitting --no-interaction && \
  php artisan migrate --force && \
  php artisan vendor:publish --force --provider="CryptaTech\\Seat\\Fitting\\FittingServiceProvider" && \
  php artisan cache:clear && \
  php artisan config:clear && \
  php artisan view:clear && \
  php artisan route:clear && \
  php artisan horizon:terminate && \
  php artisan up'
```

本轮为了快速验证，使用的是本地 dirty 文件 SFTP 同步到 `/var/www/seat/vendor/akinams053/seat-fitting`。正式发布后应回到 composer 流程。

---

## 8. 生产部署注意事项

生产服本轮没有动。生产写操作必须重新确认。

部署生产前至少确认：

```bash
scripts/ssh-seat 'cd /var/www/seat && php -v && composer --version && composer show akinams053/seat-fitting cryptatech/seat-fitting --no-interaction || true'
scripts/ssh-seat 'cd /var/www/seat && php artisan route:list | grep fitting'
scripts/ssh-seat 'cd /var/www/seat && php artisan migrate:status | grep fitting'
```

如果生产仍装的是上游 `cryptatech/seat-fitting`，要先评估替换路径和数据保留。不要直接删除生产 vendor 或跑 composer remove，除非用户明确确认。

---

## 9. 操作纪律

- 不要提交 `.creds`、`.creds.test`、`scripts/test.key`、任何私钥或凭据。
- 不要把 namespace / route name / DB prefix 重命名成新 vendor 名。
- 修改 JS/CSS 后，宿主 SeAT 必须 `vendor:publish --force`。
- 本机没有 PHP；PHP lint 要在测试服或 SeAT 宿主跑。
- 测试服可以自由验证；生产写操作必须确认。
- 当前 UI 语义以用户最新反馈为准：具体配装详情在左侧检查卡片下面；技能名不机翻。

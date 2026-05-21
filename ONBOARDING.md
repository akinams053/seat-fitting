# ONBOARDING — akinams053/seat-fitting

交接快照日期：2026-05-22。本仓库当前准备发布到 `1.8.0`；本地仍有未纳入发布的 `.gitignore` / `.claude/` 工作区状态。

这份文档让下一位接手者在 5 分钟内建立全局上下文；更底层的架构和命令约定在 `CLAUDE.md`。

---

## 1. 项目定位

`akinams053/seat-fitting` 是 SeAT 5.x 插件（fork 自 `cryptatech/seat-fitting`），不是独立 Laravel 应用。它通过 `CryptaTech\Seat\Fitting\FittingServiceProvider` 注册到宿主 SeAT。

当前业务语义：

- **配装**：单套具体 EFT 配置。`Fitting` 行带 fitting_id、name、ship_type_id。
- **配装分组**：底层 `Doctrine` 表；UI 文案统一叫"配装分组"。一个分组装多套配装；同一 fit 可在多个分组里（但通常用「复制配装→拖入新组」隔离）。**1.7.0 起每个分组有 `is_locked` 标志，可被 lock 防止误改**。
- **辅助技能方案（1.3.0 起）**：独立资源 `FittingSkillPlan`。从 EVE 游戏内技能方案文本（或纯英文 `Name N` 列表）解析得到的「技能 + 等级」清单，挂上一档（minimum 或 advanced）的 tag。
  - 挂到「分组」上：组内所有 fit 都吸收该方案（仅在那个分组的检查 context 里）
  - 挂到「fit 在某分组」上：仅在该 fit 通过那个分组检查时生效（带 `scope_doctrine_id`，1.5.0 起）
  - 方案管理 UI（CRUD + 挂载）**只在配装分组页**（1.4.0 起搬过去的）
- **个人配装检查**：成员检查自己的角色能否驾驶某 fit。1.6.0 起树上每个 fit 行带 `doctrine_id` context —— 点 D 树下的 F = 在 D context 里检查 F，避免跨组串读。
- **配装及技能管理**（旧名「配装录入」，1.3.0 重命名）：管理员维护配装本身、技能要求、复制/重命名 fit。1.4.0 起去掉了 EFT 文本显示卡和方案管理 UI。
- **军团技能检查**：1.6.0 起改成「doctrine 必选 + fit 可选筛选」单一表单，避免 doctrine/fit 二选一 radio 的歧义。
- **舰队技能审查（1.8.0 起）**：使用当前 SeAT 账号下具备 `esi-fleets.read_fleet.v1` 且正在舰队中的角色自动读取当前舰队；按配装分组 / 可选单 fit 检查技能，并按舰船/配装统计未达标、入门、进阶、未审查与手工录入的 DPS/DPH。

不要把 namespace / route name / DB 前缀改成 `akinams053`：为兼容上游安装，它们必须保留 `CryptaTech\Seat\Fitting\`、`cryptafitting::*`、`crypta_tech_seat_*`。Composer 包名 (`akinams053/seat-fitting`) 和 PSR-4 namespace **故意**不一致。

---

## 2. 当前 Git / 发布状态

| 项目 | 值 |
|---|---|
| 当前分支 | `master`，与 `origin/master` 一致 |
| 最新提交 | `1de09df feat: add fleet damage totals` |
| 最新 tag | `1.8.0`（本次正式发布） |
| Packagist | `1.8.0`（push tag 后 webhook 同步约 30 秒） |
| 本地工作区 | `.gitignore` / `.claude/` 有未纳入发布的本地状态 |

标签轨迹（按发布顺序）：
```
v1.0.0 → 1.1.0 → 1.1.1 → 1.2.0 → 1.2.1 → 1.2.2 → 1.2.3 → 1.2.4 → 1.2.5 →
1.3.0 → 1.4.0 → 1.5.0 → 1.5.1 (migration hotfix) → 1.6.0 → 1.6.1 (Sortable fix) → 1.7.0 → 1.7.1 → 1.8.0
```

**1.5.0 有 migration bug**（drop UNIQUE 被 FK 挡住），后续靠 1.5.1 的幂等 migration 修。从 1.5.0 起任何升级都跳到 1.5.1+。生产部署必须用 ≥1.5.1。

CI `.github/workflows/lint.yml` 在每次 push 后跑 `pint`，仅当有改动才 auto-commit `Fixes coding style`。push 前最好本地 `pint` 一遍。

---

## 3. 依赖

来自 `composer.json`：

| 类型 | 包 | 约束 |
|---|---|---|
| 运行时 | `eveseat/services` | `^5.0.1` |
| 运行时 | `eveseat/eveapi` | `^5.0` |
| 运行时 | `eveseat/web` | `^5.0` |
| dev | `laravel/pint` | `^1.18` |
| dev | `rector/rector` | `^1.2` |
| dev | `phpstan/phpstan` | `^1.12` |
| dev | `nunomaduro/larastan` | `^2.9` |

PHP：composer.json 不声明 `require.php`；CI 锁 PHP 8.3；测试服跑 PHP 8.4.21；实际下限 8.3。

前端：`Sortable.min.js`（`src/resources/assets/js/lib/`）只在 doctrine 工作台加载。fitting/personal 页不加载。

---

## 4. 服务提供者实际注册的东西

`FittingServiceProvider::boot()` 做以下事情：

1. `add_routes()` → `src/Http/routes.php`：`/api/v2/fitting/web/*`（JSON）+ `/fitting/*`（Web UI，含 plan CRUD、copy/rename、lock toggle 等）
2. `add_views()` → 视图命名空间 `fitting::`，路径 `src/resources/views/`
3. `add_translations()` → 翻译命名空间 `fitting`（en / fr / zh-CN）
4. `add_commands()` → `cryptatech:fittings:upgrade`（v5 之前旧配装重新走 `createFromEve` 迁移）
5. `addPublications()` → config `fitting.exportlinks.php`；default 发布 `resources/assets/{css,js}` 到 `public/web/{css,js}`
6. `addMigrations()` → 自动加载 `src/database/migrations/`
7. `registerSdeTables(...)` → 声明硬依赖 `dgmAttributeTypes`, `dgmTypeAttributes`, `dgmEffects`, `dgmTypeEffects`, `invFlags`
8. `registerPermissionBypass()` → `FITTING_BYPASS_PERMISSIONS=true` 时给所有 `fitting.*` 注入 `Gate::before` 放行（见 §11）

`register()` 阶段：合并 `fitting.config.php` 与 `fitting.sidebar.php`；注册 8 个 `fitting.*` 权限。

---

## 5. 权限与路由

`Config/Permissions/fitting.permissions.php` 声明 **8 个** 权限，均在 `military` division：

| Ability | 是否被路由 `can:` 中间件使用 | 备注 |
|---|---|---|
| `fitting.view` | ✅ 个人检查 + plan 列表/详情 | |
| `fitting.create` | ✅ 配装/方案 CRUD + 挂载 + 复制/重命名 | |
| `fitting.doctrineview` | ✅ 配装分组视图 | |
| `fitting.reportview` | ✅ 军团技能检查 | |
| `fitting.lock_doctrine` | ✅ 锁定/解锁分组（1.7.0 新增） | 独立权限：可被任何用户独立授予 |
| `fitting.fleet_review` | ✅ 舰队技能审查（1.8.0 新增） | 使用 SeAT SSO / RefreshToken 读取 ESI fleet |
| `fitting.manage` | ❌ 未接入 | 预留 |
| `fitting.corporation_report` | ❌ 未接入 | 预留 |

`fitting.manage` / `fitting.corporation_report` 是历史预留位。

---

## 6. 关键代码地图

```
src/
├── FittingServiceProvider.php      # 唯一入口
├── Config/
│   ├── fitting.config.php          # bypass_permissions 开关
│   ├── fitting.sidebar.php         # 4 个侧边栏入口
│   ├── fitting.exportlinks.php
│   └── Permissions/fitting.permissions.php  # 8 个权限
├── Http/
│   ├── routes.php                  # 2 个 group，~50 条路由
│   └── Controllers/
│       ├── ApiFittingController.php
│       └── FittingController.php   # Web UI 主控制器
├── Models/
│   ├── Fitting.php                 # 含 createFromEve() EFT 状态机；skillPlans() + doctrines() 反向关系
│   ├── FittingItem.php             # flag 字段编码 EVE invFlags 槽位
│   ├── FittingSkillRequirement.php # 入门/进阶要求，source=calculated|manual|custom
│   ├── FittingSkillPlan.php        # 辅助技能方案
│   ├── FittingSkillPlanItem.php    # 方案条目 (type_id + level)
│   ├── FittingSkillPlanAttachment.php # 方案挂载（polymorphic: fitting | doctrine）+ scope_doctrine_id
│   ├── Doctrine.php                # 配装分组（is_locked 1.7.0+）；skillPlans() 反向关系
│   ├── OldFitting.php / OldDoctrine.php  # 只读，给 UpgradeFits 命令用
│   └── Sde/                        # DgmTypeEffect, InvFlag SDE 视图
├── Services/
│   ├── SkillRequirementCalculator.php
│   ├── SkillRequirementSyncService.php
│   ├── CharacterSkillSnapshotService.php   # 按需加载角色技能（减内存）
│   ├── SkillPlanParser.php                 # 解析 EVE 方案文本
│   ├── PersonalSkillCheckService.php       # 入口：effectiveRequirementsForTier + normalizeAdvancedAgainstMinimum
│   ├── CorporationSkillReportService.php   # 按 SeAT 账号聚合
│   ├── FleetEsiService.php                 # 使用 SeAT RefreshToken 自动读取当前舰队
│   └── FleetSkillReviewService.php         # 舰队成员技能审查 + 舰船/火力统计
├── Helpers/CalculateConstants.php
├── Commands/UpgradeFits.php
├── Validation/
├── Events/                         # FittingUpdated, DoctrineUpdated
├── database/migrations/            # 9 个迁移
└── resources/
    ├── views/                      # fitting / doctrine / doctrinereport + includes/
    │   └── includes/
    │       ├── display-fit.blade.php          # 槽位列表 + plan-attached-block include（默认折叠 1.4.0+）
    │       ├── display-skills.blade.php       # 技能检查 + 管理编辑器 + 未达标导出模态
    │       ├── plan-attached-block.blade.php  # attached-plans 只读卡片区
    │       ├── plan-edit-modal.blade.php      # 新建/编辑方案
    │       └── fit-rename-modal.blade.php
    ├── assets/{js,css}/
    │   └── js/
    │       ├── fitting.js          # 个人/管理页主逻辑（树 / 技能检查 / 编辑器）
    │       ├── plans.js            # 方案 modal + API helper + 只读 attached 卡片渲染 + 颜色 palette
    │       └── doctrine.js         # 分组工作台 + plan CRUD + Sortable wiring + 锁定 UI
    └── lang/{en,fr,zh-CN}/
```

迁移文件（按时间序）：
```
2024_01_11_000000_create_crypta_tech_seat_fittings.php
2024_01_11_000001_create_crypta_tech_seat_fitting_items.php
2024_01_12_000000_create_crypta_seat_doctrine_table.php
2026_05_20_000000_create_crypta_tech_seat_fitting_skill_requirements.php
2026_05_25_000000_create_crypta_tech_seat_fitting_skill_plans.php
2026_05_25_010000_add_scope_doctrine_id_to_plan_attachments.php   # 1.5.1 起幂等版
2026_05_25_020000_add_is_locked_to_crypta_tech_seat_fitting_doctrine.php   # 1.7.0
2026_05_25_030000_add_damage_metrics_to_fittings.php   # 1.8.0，minimum/advanced DPS/DPH
```

所有 migration 必须**幂等**（MySQL ALTER 非事务）。drop 索引前若它覆盖了 FK 列要先加单列补位索引。详见 `CLAUDE.md` 末尾的「migration 守则」段。

---

## 7. 辅助技能方案（核心特性）

### 数据模型（3 张表）

```
crypta_tech_seat_fitting_skill_plans
  id, name, description, tier ENUM('minimum','advanced'), timestamps

crypta_tech_seat_fitting_skill_plan_items
  id, plan_id (FK→plans cascade), skill_type_id, level
  UNIQUE(plan_id, skill_type_id)

crypta_tech_seat_fitting_skill_plan_attachments
  id, plan_id (FK→plans cascade)
  attachable_type ENUM('fitting','doctrine')
  attachable_id (fitting_id 或 doctrine_id)
  scope_doctrine_id (nullable, 1.5+)
  UNIQUE(plan_id, attachable_type, attachable_id, scope_doctrine_id)
```

`scope_doctrine_id` 的语义（1.5+）：
- `attachable_type='doctrine'` → scope 总是 NULL（组级挂载）
- `attachable_type='fitting'` + scope=NULL → 通用直接挂载（1.4.0 之前唯一形式，迁移时已清空残余）
- `attachable_type='fitting'` + scope=D → "fit 在 D 这个分组里挂的"，仅在 D context 生效

### Polymorphic + 手动清理（无 FK 级联）

`attachable_id` 没 DB 级 FK（指向不同表）。删 fit / 删 doctrine / fit 从 doctrine 移除时**控制器手动清** attachments：
- `deleteFittingById` 按 `attachable_id=fittingId` 清所有
- `deleteDoctrine` / `delDoctrineById` 按 `attachable_type='doctrine' AND attachable_id=doctrineId` + `scope_doctrine_id=doctrineId` 双清
- `detachFittingFromDoctrine`（fit 从 D 移除）按 `attachable_id=fittingId AND scope_doctrine_id=D` 清

任何新增的「删除目标」路径必须照样清，否则会留孤儿行。

### 解析器（`SkillPlanParser`）

兼容两种行格式：
```
<localized hint="Gunnery">射击学*</localized> 5    # EVE 客户端导出
Gunnery 5                                         # 清洗后或手写
```

按 typeName 在 `invTypes`（category=SKILL, published=true）查 type_id；同名多行 MAX 折叠；无法识别行返回 `unmatched`。

### 生效合并（`effectiveRequirementsForTier`）

```
effective[tier] = base
              ⊎ MAX(直接挂 scope=NULL 或 scope=ctx 的 plan items 的本档项)
              ⊎ MAX(ctx doctrine 的 plan items 的本档项)
```

`ctx` = `contextDoctrineId` 参数：
- 个人单 fit 检查（personal page 单 fit 点击）：ctx = 用户点击树时所在 doctrine row 的 id（1.6.0 起每行带 `data-doctrine-id`）
- 个人分组检查：ctx = 该 doctrine.id
- 军团检查（doctrine）：ctx = 该 doctrine.id
- 军团检查（doctrine + 单 fit 筛选）：ctx = 该 doctrine.id
- ungrouped fit（在树的"未分组"段点击）：ctx = null，只用 scope=NULL 直接挂载

### 进阶 ≥ 入门 自动 normalize（1.7.1 起）

`PersonalSkillCheckService::normalizeAdvancedAgainstMinimum($min, $adv)`：对每个进阶项，若同 typeId 入门 level 更高，把进阶 level 抬到入门（**输出 only，不写库**）。打 `auto_raised_to_minimum: true` 标记。

四处调用：
- `checkForCurrentUser` 两档 effective 之后
- `checkDoctrineForCurrentUser` 每 fit 两档 effective 之后
- `CorporationSkillReportService::runForFittings` 喂 skillMap 之前
- `FittingController::getFittingRequirements` 返回前（编辑器拉数据规范化）

还有一处**写库**的规范化：`FittingController::saveFittingRequirements` 用私有 `normalizeAdvancedPayloadAgainstMinimum`（按 skill_type_id 索引）在 persist 前抬高 payload 中 adv level——防御网，挡 UI 没拦住的 inversion。

前端管理编辑器（`fitting.js`）：进阶 tier 渲染时下拉只列 ≥ 入门 level 的选项；选中值 `max(stored, floor)`；监听入门表变化（add/remove/change level）→ 实时刷新对应 typeId 的进阶行下拉。

效果：用户**永远看不到「进阶 ✓ 但入门 ✕」的矛盾状态**。

### `requirementsForTier` vs `effectiveRequirementsForTier`

- `requirementsForTier`（基础值）：编辑器读 base 用 —— GET `/fitting/{id}/requirements`
- `effectiveRequirementsForTier`（合并后）：所有检查路径用

### UI 入口

- 「配装及技能管理」页：仅管理 fit 本体；左侧详情下方 attached-plans 块**只读**展示该 fit 在当前 context 下的方案；不再有方案 CRUD UI（1.4.0 起搬走）
- 「个人配装检查」页：fit 树 + 右侧技能检查；attached-plans 块只读
- 「配装分组」工作台：
  - 左：分组列表，每组带 lock 按钮（canLock 用户可见）+ rename + delete
  - 右上：fit 池（从这里拖 fit 进分组）
  - 右下：plan 池 + 「+ 新建方案」（canCreate）
  - 每个分组下方有「拖入方案到此，应用辅助技能到此分组」拖入区
  - 每个 fit 卡片右侧有 per-fit 拖入区
  - 锁定的分组：所有修改 UI 隐藏、Sortable 跳过

### Lock 守门（1.7.0）

每个分组 `is_locked` 字段。`POST /doctrine/{id}/lock` toggle（需 `fitting.lock_doctrine`）。`abortIfDoctrineLocked` helper 在所有改 doctrine 的端点首部调用，锁定时 423 + 翻译错误。守门点：rename / delete / delDoctrineById / attachFittingToDoctrine / detachFittingFromDoctrine / attachPlanToDoctrine / detachPlanFromDoctrine / attachPlanToFittingInDoctrine / detachPlanFromFittingInDoctrine。

---

## 8. 已落地的功能（按版本顺序）

| Tag | 摘要 |
|---|---|
| `v1.0.0` | fork 初始 release，移除上游价格供应商依赖、品牌切换、加 zh-CN |
| `1.1.0` | `FittingSkillRequirement` 模型 + 入门/进阶两档 + 4 个 Service；技能要求 UI |
| `1.1.1` | 军团报告内存优化（先聚集需求技能、只加载相关行） |
| `1.2.0` | UI 大改：父子导航 / tab 化技能检查 / 5 格分段等级条 |
| `1.2.1`–`1.2.5` | 报告页修复、按 SeAT 账号聚合、`FITTING_BYPASS_PERMISSIONS` 开关 |
| `1.3.0` | **辅助技能方案** + 复制/重命名 + 未达标导出 + 菜单改名 |
| `1.4.0` | 方案 CRUD/挂载搬到 doctrine 页；去掉 EFT 显示；fit 详情默认折叠；per-fit 卡片右侧拖入区 |
| `1.5.0` / `1.5.1` | **`scope_doctrine_id`** 修跨组方案串读；1.5.1 是 migration 幂等修复 |
| `1.6.0` | **per-row doctrine context**（树每行带 doctrine_id）+ corp report 改 doctrine+fit 筛选 + plan 整卡背景上色 |
| `1.6.1` | Sortable `put: true` → 严格白名单，修 plan 拖进 fit 列表变 fit 的串泄 |
| `1.7.0` | **配装分组 lock**（is_locked + `fitting.lock_doctrine` 权限 + 9 个端点守门 + UI 适配） |
| `1.7.1` | **进阶 ≥ 入门 自动 normalize**（编辑器下拉过滤 + 保存时抬高 + 检查时抬高） |
| `1.8.0` | **舰队技能审查正式上线**：SeAT SSO 自动识别当前舰队、配装 DPS/DPH 录入、按舰船/配装统计合格人数与整队 DPS/DPH，未匹配舰船单列“未进行审查” |

### 当前 UI 语义约束（必须保留）

- 个人配装检查里，具体配装详情在**左侧**检查/录入卡片下方，不在右侧
- 普通个人检查不把 EFT 文本作为主视图（1.4.0 起已去除 EFT 显示）
- 技能名和技能分类用 EVE SDE 官方英文 `typeName` / `groupName`，**不机翻**
- 军团技能检查含「昵称」列，来源 `character_infos.title`
- attached-plans 卡片始终在左侧（fit 详情下方），不在右侧
- 分组检查时 attached-plans 块要 `.hide()` 避免残留误导
- 方案管理 UI **只在配装分组页**，不在管理页（1.4.0 起）
- 每个 fit 树行的 `data-doctrine-id` 是隔离同 fit 跨组 plan 串读的关键（1.6.0 起），不要去掉
- 进阶 level 始终 ≥ 入门 level（自动 normalize，1.7.1 起）

---

## 9. 测试服

通过 `scripts/ssh-seat -t test 'cmd'` 操作。

| 项目 | 值（截至 2026-05-22 1.8.0 测试服部署后） |
|---|---|
| SeAT 路径 | `/var/www/seat` |
| 插件路径 | `/var/www/seat/vendor/akinams053/seat-fitting` |
| Web URL | `http://ylxh.de` |
| APP_ENV | `local`，APP_DEBUG enabled |
| PHP | `8.4.21` |
| composer 装的插件版本 | `akinams053/seat-fitting dev-master 1de09df`（正式 tag 后可切到 `1.8.0`） |
| `FITTING_BYPASS_PERMISSIONS` | `true`（任何登录用户都能看 UI、也能 lock 分组）|

认证方式：`.creds.test` 第 4 行写 `scripts/test.key`，`ssh_seat.py` 见 `/` 自动走 key 认证。换机器要同步 `.creds.test` + `scripts/test.key` 并 `chmod 600`。

部署走 composer（不要 SFTP 直贴）：

```bash
scripts/ssh-seat -t test 'cd /var/www/seat && \
  sudo -u www-data php artisan down && \
  sudo -u www-data composer update akinams053/seat-fitting --with-dependencies --no-interaction --prefer-dist && \
  sudo -u www-data php artisan migrate --force && \
  sudo -u www-data php artisan vendor:publish --force --provider="CryptaTech\\Seat\\Fitting\\FittingServiceProvider" && \
  sudo -u www-data php artisan config:clear && \
  sudo -u www-data php artisan cache:clear && \
  sudo -u www-data php artisan view:clear && \
  sudo -u www-data php artisan route:clear && \
  sudo -u www-data php artisan up'
```

> **注意**：`php artisan` 必须以 `sudo -u www-data` 运行（直接 root 跑会让 psysh 写 `/var/www/.config/...` 权限失败）。`tinker` 同理。要在 SSH 跑 PHP 探查脚本，要么写 artisan 自定义命令，要么用 mysql 客户端读 DB。

切换 `FITTING_BYPASS_PERMISSIONS` 后必须 `php artisan config:clear`。

**Packagist 同步延迟**：push 新 tag 后 composer update 可能拉不到，等 ~30 秒重试，或 `curl https://repo.packagist.org/p2/akinams053/seat-fitting.json` 验证。

---

## 10. 生产服

通过 `scripts/ssh-seat 'cmd'`（默认读 `.creds`）。

生产规则（**严格**）：

- **任何写操作**（migrate / publish / queue restart / composer / 文件修改）必须事先和用户确认
- `FITTING_BYPASS_PERMISSIONS` **不要设置**，或显式 `=false`
- 验证：
  ```bash
  scripts/ssh-seat 'cd /var/www/seat && grep FITTING_BYPASS_PERMISSIONS .env || echo "(unset = safe default)"'
  ```
  应是 `(unset = safe default)` 或 `=false`。看到 `=true` 立刻提醒并问要不要改
- **1.5.0 的 migration 有 bug** —— 任何生产升级必须用 ≥1.5.1（1.5.1 是幂等版的同一个 migration）
- 1.3.0 / 1.5.0 / 1.7.0 / 1.8.0 都引入新表或新列，对应升级**必须跑 `php artisan migrate`**
- 如果生产仍装的是上游 `cryptatech/seat-fitting`，要先评估替换路径和数据保留（namespace、DB 前缀、route name 全部沿用上游正是为了这种切换）。**不要**直接 `composer remove cryptatech/seat-fitting`，除非用户明确确认
- 读类命令（`composer show` / `route:list` / `migrate:status`）可直接跑

---

## 11. 权限旁路开关（FITTING_BYPASS_PERMISSIONS）

`1.2.5` 起插件支持环境变量 `FITTING_BYPASS_PERMISSIONS`。

| 值 | 行为 |
|---|---|
| `true` | 服务提供者注册 `Gate::before`，所有 `fitting.*`（含 `fitting.lock_doctrine`）直接放行 |
| `false`（默认） | 走 SeAT 标准角色权限 |

实现：`src/Config/fitting.config.php` 暴露 `bypass_permissions`；`FittingServiceProvider::registerPermissionBypass()` 在 boot 时检查并注册 `Gate::before`。

测试服开、生产服关（详见 §9 / §10）。

为什么存在：测试服没给每个测试账户配 SeAT 角色，又希望军团成员能直接打开 UI。生产服角色配置齐全，必须强制按权限放行避免越权。这是单点 staging-only escape hatch，**不要**靠它做生产场景的「允许所有军团成员看」——那是 SeAT 角色配置的事。

---

## 12. 操作纪律

- **不要提交**：`.creds`、`.creds.test`、`scripts/test.key`、任何 `*.key` / `*.pem`
- **不要重命名** namespace / route name / DB 前缀到新 vendor 名
- **修改 JS / CSS 后**，宿主 SeAT 必须 `php artisan vendor:publish --force --provider="CryptaTech\\Seat\\Fitting\\FittingServiceProvider"`
- **本机没有 PHP**：PHP lint 要在测试服跑（`find vendor/akinams053/seat-fitting/src -name "*.php" -print0 | xargs -0 -n1 php -l`）；JS 可以本机 `node --check`
- **CI**：`.github/workflows/lint.yml` 每次 push 跑 `pint`，仅当有改动 auto-commit `Fixes coding style`
- **新增「删除目标」路径必须清 plan attachments**（§7 polymorphic 段）
- **migration 必须幂等 + drop UNIQUE 前检查 FK 覆盖**（详见 `CLAUDE.md` 末段，1.5.0 撞过这个坑）
- **测试服可以自由验证；生产写操作必须确认**

---

## 13. 已知未完成 / 后续候选方向

- **舰队审查进一步增强**：当前按成员当前 hull 匹配配装，不能证明成员实际 fitting 完全一致；DPS/DPH 是管理员手工录入，不从 EFT 自动解析。
- **预留权限**：`fitting.manage` 和 `fitting.corporation_report` 仅声明、未接入。要么把现有路由迁过去做更细粒度的访问控制，要么从权限文件删掉
- **composer.json 不锁 PHP 版本**：见 §3
- **`composer.lock` 不进 git**（`.gitignore` 已盖住）——这是 SeAT 插件惯例
- **effectiveRequirementsForTier 有潜在 N+1**：当 doctrine 含 N 个 fit 时按 fit 各查一次 plans + doctrines；当前数据规模（15 fit / 400 人）实测无感（参考 1.1.1 基准），但若未来 fit 数大涨可以预取整个 doctrine 的 plans 一次发出去
- **`eft-export.blade.php` 文件还在但无人 include**（1.4.0 起）：留以防 zKill 等外部 export link 复活；下版可以彻底删
- **`fitting::fitting.plan_attached_manage_hint` 等翻译键不再被用**：保留无害，下次清理
- **`auto_raised_to_minimum` 标记输出到前端但没用**（1.7.1 起）：UI 可以加小提示告诉用户「该项已自动抬到入门级」，目前未做

# ONBOARDING — akinams053/seat-fitting

交接快照日期：2026-05-20。本仓库当前已发布到 `1.3.0`；工作区基本干净（`.creds.example` 已恢复、`.claude/` 是本地 IDE 配置，未追踪也不入 git）。

这份文档让下一位接手者在 5 分钟内建立全局上下文；更底层的架构和命令约定在 `CLAUDE.md`。

---

## 1. 项目定位

`akinams053/seat-fitting` 是 SeAT 5.x 插件（fork 自 `cryptatech/seat-fitting`），不是独立 Laravel 应用。它通过 `CryptaTech\Seat\Fitting\FittingServiceProvider` 注册到宿主 SeAT。

当前业务语义：

- **配装**：单套具体 EFT 配置。
- **配装分组**：底层仍是 `Doctrine` 表，UI 文案统一叫"配装分组"，作用是把多套具体配装放进一个组。
- **辅助技能方案（1.3.0 新增）**：独立资源，从 EVE 游戏内技能方案文本解析得到的「技能列表 + 等级 + 档次（入门/进阶）」三元组合。可以挂到单个配装，也可以挂到配装分组（继承到该组所有配装）。挂上后只能**抬高**对应档次的技能要求门槛，不会降低。
- **个人配装检查**：普通成员检查自己的角色是否满足单配装或配装分组要求。检查所用的「有效要求」=配装自身要求 ∪ 直接挂载方案 ∪ 通过分组继承的方案，按 typeId 做 MAX 合并。
- **配装及技能管理**（旧名「配装录入」，1.3.0 重命名）：管理员维护配装、技能要求、方案。同一页内还能复制 / 重命名配装。
- **军团技能检查**：管理者按配装分组、联盟/公司维度批量检查成员技能；与个人检查共享同一套「有效要求」合并逻辑。
- **舰队审查**：仅是占位（`fleet_review` 权限声明了但无任何路由接入），尚未实现。

不要把 namespace / route name / DB 前缀改成 `akinams053`：为兼容上游安装，它们必须保留 `CryptaTech\Seat\Fitting\`、`cryptafitting::*`、`crypta_tech_seat_*`。`composer.json` 的 `name` 字段（`akinams053/seat-fitting`）和 PSR-4 namespace（`CryptaTech\Seat\Fitting\`）**故意**不一致。

---

## 2. 当前 Git / 发布状态

| 项目 | 值 |
|---|---|
| 当前分支 | `master`，与 `origin/master` 一致 |
| 最新提交 | `9d7f71e feat: auxiliary skill plans, fitting copy/rename, missing-skills export` |
| 最新 tag | `1.3.0`（已 push） |
| Packagist | `1.3.0` |
| 本地工作区 | 干净；`.claude/` 是 IDE 本地配置不入 git |

标签轨迹：`v1.0.0` → `1.1.0` → `1.1.1` → `1.2.0` → `1.2.1` → `1.2.2` → `1.2.3` → `1.2.4` → `1.2.5` → `1.3.0`。前 9 个标签集中在 2026-05-20 内连发，`1.3.0` 是同日晚些时候的功能版本。

CI `.github/workflows/lint.yml` 在每次 push 后跑 `pint`，**仅当有改动**才会 auto-commit `Fixes coding style`。`1.3.0` push 时 90 秒内 CI 未推新 commit，说明已合规；本地 push 前最好仍跑 `pint` 一遍。

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
| dev | `driftingly/rector-laravel` | `^1.2` |
| dev | `phpstan/phpstan` | `^1.12` |
| dev | `nunomaduro/larastan` | `^2.9` |

**PHP 版本注意**：

- `composer.json` 没有声明 `require.php`，所以 Composer 不会强制版本。
- CI（`.github/workflows/lint.yml`）矩阵锁 `php: [8.3]`，CLAUDE.md 里也写"目标 PHP 8.3"。
- 测试服实际跑 `PHP 8.4.21`（见 §8）。
- 实际跑得起来的下限是 8.3（与上游 `eveseat/web ^5.0` 一致），但当前没有任何声明强制 —— 如果以后要锁，可以在 `composer.json` 里补 `"php": "^8.3"`。

价格供应商依赖（上游 `cryptatech/seat-fitting` 有）已在 `bcd46bb` 中移除，不要重新引入。

前端：`Sortable.min.js`（已存在于 `src/resources/assets/js/lib/`）在 1.3.0 起被同时用于配装分组工作台（fittings + plans 双拖拽源）与「配装及技能管理」页（plans → fittings 拖拽）。个人检查页**不**加载 Sortable。

---

## 4. 服务提供者实际注册的东西

`FittingServiceProvider::boot()` 做以下事情：

1. `add_routes()` —— include `src/Http/routes.php`，定义两个 route group：
   - `/api/v2/fitting/web/*` → `ApiFittingController`（JSON）
   - `/fitting/*` → `FittingController`（Web UI，含 1.3.0 新增的 plan CRUD / copy / rename 端点）
2. `add_views()` —— 视图命名空间 `fitting::`，路径 `src/resources/views/`。
3. `add_translations()` —— 翻译命名空间 `fitting`，已落地 `en` / `fr` / `zh-CN`。
4. `add_commands()` —— 注册 `cryptatech:fittings:upgrade`（`UpgradeFits`），用于把 v5 之前的旧配装重新走 `Fitting::createFromEve()` 迁移。
5. `addPublications()` —— `config` tag 发布 `fitting.exportlinks.php`；默认 tag 发布 `resources/assets/{css,js}` 到 `public/web/{css,js}`。
6. `addMigrations()` —— 自动加载 `src/database/migrations/` 下所有文件。
7. `registerSdeTables(...)` —— 声明硬依赖 `dgmAttributeTypes`, `dgmTypeAttributes`, `dgmEffects`, `dgmTypeEffects`, `invFlags`。**宿主必须导入这些 SDE 表，否则插件不可用。**
8. `registerPermissionBypass()` —— `FITTING_BYPASS_PERMISSIONS=true` 时为所有 `fitting.*` ability 注入 `Gate::before` 放行。详见 §10。

`register()` 阶段：

- 合并 `fitting.config.php` 到 `fitting.config.*`
- 合并 `fitting.sidebar.php` 到 `package.sidebar`（4 个入口）
- `registerPermissions(...)` 注册 7 个 `fitting.*` 权限

---

## 5. 权限与路由（重要技术债）

`Config/Permissions/fitting.permissions.php` 声明了 **7 个** 权限，全部归入 `military` division：

| Ability | 是否被任何路由 `can:` 中间件实际使用 |
|---|---|
| `fitting.view` | ✅ 用于个人检查相关路由、plan 列表 / 详情 / 个人检查的方案展示 |
| `fitting.create` | ✅ 用于配装录入、技能要求编辑、配装分组编辑、**plan CRUD**、**配装复制 / 重命名** |
| `fitting.doctrineview` | ✅ 用于配装分组视图 |
| `fitting.reportview` | ✅ 用于军团技能检查 |
| `fitting.manage` | ❌ **未接入任何路由** |
| `fitting.corporation_report` | ❌ **未接入任何路由** |
| `fitting.fleet_review` | ❌ **未接入任何路由**（功能本身也未实现） |

后 3 个是预留位（参考 `9eeae6f feat: add managed skill requirements` 当时打算迁到更细粒度），实际拦截仍走旧的粗粒度 4 个 ability。1.3.0 没动这块。下一次权限细化时要么把路由迁过去，要么把未用的权限删掉，不要让这种"声明了但没用"的状态长期存在。

---

## 6. 关键代码地图

```
src/
├── FittingServiceProvider.php      # 唯一入口
├── Config/
│   ├── fitting.config.php          # bypass_permissions 开关
│   ├── fitting.sidebar.php         # 4 个侧边栏入口
│   ├── fitting.exportlinks.php
│   └── Permissions/fitting.permissions.php  # 7 个权限声明
├── Http/
│   ├── routes.php                  # 2 个 group, ~40 条路由
│   └── Controllers/
│       ├── ApiFittingController.php   # JSON API (代理到 FittingController 静态方法)
│       └── FittingController.php      # Web UI 主控制器 (含 1.3.0 plan/copy/rename 端点)
├── Models/
│   ├── Fitting.php                 # 含 createFromEve() EFT 状态机解析；新增 skillPlans() + doctrines() 反向关系
│   ├── FittingItem.php             # flag 字段编码 EVE invFlags 槽位
│   ├── FittingSkillRequirement.php # 入门/进阶要求，source=calculated|manual|custom
│   ├── FittingSkillPlan.php        # ⭐ 1.3.0 辅助技能方案
│   ├── FittingSkillPlanItem.php    # ⭐ 1.3.0 方案条目 (type_id + level)
│   ├── FittingSkillPlanAttachment.php # ⭐ 1.3.0 方案挂载 (polymorphic: fitting | doctrine)
│   ├── Doctrine.php                # 配装分组（DB 层旧名）；新增 skillPlans() 反向关系
│   ├── OldFitting.php / OldDoctrine.php  # 只读，给 UpgradeFits 命令用
│   └── Sde/                        # DgmTypeEffect, InvFlag SDE 视图
├── Services/                       # 全部 stateless
│   ├── SkillRequirementCalculator.php   # SDE 技能要求递归计算
│   ├── SkillRequirementSyncService.php  # 持久化 calculated 行，不覆盖 manual/custom
│   ├── CharacterSkillSnapshotService.php # 按需加载角色技能（减内存）
│   ├── SkillPlanParser.php              # ⭐ 1.3.0 解析 EVE 方案文本到 typeId/level
│   ├── PersonalSkillCheckService.php    # 入口：effectiveRequirementsForTier() 做 plan MAX 合并
│   └── CorporationSkillReportService.php # 按 SeAT 账号聚合，含 character_infos.title 昵称；1.3.0 起使用合并后的 effective requirements
├── Helpers/CalculateConstants.php  # 状态机用的 effect/attribute ID 常量
├── Commands/UpgradeFits.php        # artisan cryptatech:fittings:upgrade
├── Validation/                     # 表单校验
├── Events/                         # FittingUpdated, DoctrineUpdated
├── database/migrations/            # 5 个迁移
└── resources/
    ├── views/                      # Blade，fitting/doctrine/doctrinereport + includes/
    │   └── includes/
    │       ├── display-fit.blade.php          # 槽位列表 + 新增 plan-attached-block include
    │       ├── display-skills.blade.php       # 技能检查面板 + 1.3.0 export-missing 模态
    │       ├── plan-attached-block.blade.php  # ⭐ 1.3.0 attached-plans 区
    │       ├── plan-edit-modal.blade.php      # ⭐ 1.3.0 新建/编辑方案
    │       └── fit-rename-modal.blade.php     # ⭐ 1.3.0 重命名配装
    ├── assets/{js,css}/            # 改完必须在宿主跑 vendor:publish --force
    │   └── js/
    │       ├── fitting.js          # 技能检查主逻辑 + 1.3.0 训练时间/导出/复制/重命名钩子
    │       ├── plans.js            # ⭐ 1.3.0 方案 CRUD/拖拽
    │       └── doctrine.js         # 分组工作台 + 1.3.0 plan 池/拖拽
    └── lang/{en,fr,zh-CN}/
        ├── config.php              # menu_fitting_manage → "配装及技能管理"
        ├── fitting.php             # +20+ 个 plan/export/rename i18n keys
        └── doctrine.php            # +6 个 plan-pool keys
```

迁移文件：

```
2024_01_11_000000_create_crypta_tech_seat_fittings.php
2024_01_11_000001_create_crypta_tech_seat_fitting_items.php
2024_01_12_000000_create_crypta_seat_doctrine_table.php
2026_05_20_000000_create_crypta_tech_seat_fitting_skill_requirements.php
2026_05_25_000000_create_crypta_tech_seat_fitting_skill_plans.php   ⭐ 1.3.0
```

---

## 7. 辅助技能方案（1.3.0 核心特性）

**数据模型**（3 张新表）：

```
crypta_tech_seat_fitting_skill_plans
  id, name, description, tier ENUM('minimum','advanced'), timestamps

crypta_tech_seat_fitting_skill_plan_items
  id, plan_id (FK→plans cascade), skill_type_id, level
  UNIQUE(plan_id, skill_type_id)

crypta_tech_seat_fitting_skill_plan_attachments
  id, plan_id (FK→plans cascade), attachable_type ENUM('fitting','doctrine'), attachable_id
  UNIQUE(plan_id, attachable_type, attachable_id)
  INDEX(attachable_type, attachable_id)
```

**polymorphic attachments 的注意点**：`attachable_id` 没有 DB 级 FK（指向 fittings 或 doctrines 两张不同表）。删除 fitting / doctrine 时不会级联清理 attachment 行 —— 因此 `FittingController::deleteFittingById` / `delDoctrineById` / `deleteDoctrine` 都在事务里手动 `where(attachable_type=…, attachable_id=…)->delete()`。新增任何「删除目标」路径都要照样清理，否则会留孤儿行污染 plan 列表的挂载计数。

**解析器**（`Services/SkillPlanParser.php`）兼容两种行格式：

```
<localized hint="Gunnery">射击学*</localized> 5    # EVE 客户端导出
Gunnery 5                                         # 清洗后或手写
```

按 typeName 在 `invTypes`（约束 `categoryID = SKILL` 且 `published = true`）查 type_id；同名多行按 MAX 折叠；无法识别行返回 `unmatched` 让前端展示。Skill 名含尾随 `*` 会被去掉（不影响匹配）。

**生效合并**（`PersonalSkillCheckService::effectiveRequirementsForTier()`）：

```
effective[tier] = base ∪ plans_attached_to_fitting ∪ plans_inherited_from_doctrines
```

- 仅当 `plan_level > base_level` 时抬高，否则忽略（spec 明文「重复则不管」）。
- 抬高时给该项打 `source: 'plan'` 和 `planIds: [..]`，前端可据此渲染「来自方案 X」追因。
- `planIds` 只含真正贡献的 plan（plan_level 严格大于 base_level 的那些）；不会列出"也提到了这项但没抬高"的 plan。
- 同一 plan 同时通过「直接挂载」和「分组继承」出现时，用 `unique('id')` 去重一次。
- 不同 plan 都抬高同一 typeId 时取它们各自 level 的 max；planIds 包含所有抬高它的 plan。

**`requirementsForTier` 与 `effectiveRequirementsForTier` 的分工**：

- `requirementsForTier`（基础值）：编辑器用 —— 管理页 GET `/fitting/{id}/requirements` 走这条，让用户编辑的是原始 `FittingSkillRequirement` 行，不含 plan 注入项。
- `effectiveRequirementsForTier`（合并后）：个人检查 + 军团检查用 —— UI 展示的是有效门槛。

`CorporationSkillReportService` 在 1.3.0 起也调用 `effectiveRequirementsForTier`，所以 plan 挂到 doctrine 后军团报告会同步抬高门槛。

**UI 入口与拖拽**：

- 「配装及技能管理」页（manage mode）：
  - 顶部行：左侧配装树 + 右侧技能检查 + 技能要求编辑器（沿用 1.2.x 布局）
  - 底部行（**1.3.0 新增**）：全宽「辅助技能方案」面板，列出所有方案的卡片（Sortable 源，`pull: 'clone'`）
  - 选中配装后，左侧配装详情下方会出现 `attached-plans-block`（Sortable 目标，`put: ['plans']`），把方案卡片拖入即挂载，✕ 解绑
  - 配装树每行有 4 个动作按钮：重命名 / 复制 / 编辑 EFT / 删除
- 「个人配装检查」页：
  - 选中单个配装时，左侧详情下方只读展示已挂方案卡片（含「来自方案」/「来自分组」标识）
  - 分组检查时每个配装卡片顶部以小型 chip 标出该 fit 上挂的方案
  - 个人模式**不加载 Sortable**，没有挂载操作
- 「配装分组」工作台：
  - 右侧两块卡片：配装池（沿用）+ 方案池（**1.3.0 新增**）
  - 每个分组卡片下方出现 `doctrine-group-plans-body` Sortable 目标，拖入方案即挂到该组（继承到组内所有配装）

**未达标技能导出 + 训练时间**（1.3.0 新增，不仅服务于 plan 用户）：

- 个人检查每档面板顶部显示「未达标技能合计训练时间」（按 `rank × 250 × 5.66^(L-1)` 求和差额，与 EVE 训练队列一致的公式）。
- 「导出未达标」按钮打开模态，文本框内每个未达标技能从 `current+1` 到 `required` 各打一行 `EnglishName N`，可直接粘贴回 EVE 客户端导入训练队列，也兼容本插件的 `SkillPlanParser` 反向导入。
- 实现走 `ExportCache`（按 panel id 缓存技能列表），每次 `renderSkillCheck` 入口清空避免泄漏。

**配装复制 / 重命名**：

- 复制：`POST /fitting/fittings/{id}/copy` 事务里克隆 Fitting 行 + 所有 FittingItem + 所有 FittingSkillRequirement + 所有直接挂载的 plan attachments。新名字 `{原名} (副本)`；若重名自动追加 `2`、`3`…。**注意**：分组归属不复制（新 fit 不进任何分组，需要手动加入），所以分组继承的 plan 也不会跟来。
- 重命名：`PATCH /fitting/fittings/{id}`，仅改 name。

---

## 8. 已落地的功能（按版本顺序）

| Tag | 摘要 |
|---|---|
| `v1.0.0` | fork 初始 release，移除上游价格供应商依赖、更换品牌、加 zh-CN 翻译 |
| `1.1.0` | 加入 `FittingSkillRequirement` 模型 + 入门/进阶两套要求 + 4 个 Service，技能要求支持新增/删除/改等级/保存；引入技能分类下拉 + 技能搜索接口（`/fitting/skill-groups`、`/fitting/skills/search`）；只允许 SDE category `skill` + `published=true` 的 type 保存 |
| `1.1.1` | 军团报告内存优化：先汇总所需技能，再只加载这些技能，464 人 / 2 配装分组 / 1 配装可在 0.5 秒内跑完，峰值约 60 MB |
| `1.2.0` | UI 大改：父子导航、tab 化技能检查、左侧检查卡片下方显示具体配装详情；5 格分段技能等级条；分类按缺口自动展开 |
| `1.2.1` | 报告页修复：DataTables 初始化、进度条样式、入门/进阶颜色互换 |
| `1.2.2` | 1.2.x review-pass 微调（CSS、JS 小修） |
| `1.2.3` | 军团报告按 SeAT 账号（角色拥有者）聚合，统一等级 chip 文案 |
| `1.2.4` | 修复 soft-deleted refresh token 导致的同账号分组遗漏 |
| `1.2.5` | ×N 计数器从灰字升级为内嵌粗体药丸；新增 `FITTING_BYPASS_PERMISSIONS` staging-only 旁路开关（详见 §10） |
| `1.3.0` | **辅助技能方案**（CRUD + 解析器 + tier-aware MAX 合并 + 拖拽挂载到 fit/doctrine）+ **配装复制 / 重命名** + **未达标技能导出 + 训练时间汇总** + 菜单文案「配装录入」→「配装及技能管理」。详见 §7。 |

当前 UI 语义约束（必须保留）：

- 个人配装检查里，具体配装详情在**左侧**检查/录入卡片下方，不在右侧技能检查下方。
- 普通个人检查不把 EFT 文本作为主视图，EFT 是管理/导出的事。
- 技能名和技能分类用 EVE SDE 官方英文 `typeName` / `groupName`，**不机翻**。SeAT SDE 当前没有 `trn*` 翻译表，`invTypes` 也无本地化字段。
- 军团技能检查含"昵称"列，来源 `character_infos.title`。
- **1.3.0 起**：已挂方案卡片在左侧配装详情下方，不放右侧；配装分组检查时该区域必须 `.hide()` 避免单 fit 残留卡片误导；方案管理界面**仅出现在 manage 模式**，个人模式只读。

---

## 9. 测试服

通过 `scripts/ssh-seat -t test 'cmd'` 操作。

| 项目 | 值（截至 2026-05-20 1.3.0 部署后，已远程核对） |
|---|---|
| SeAT 路径 | `/var/www/seat` |
| 插件路径 | `/var/www/seat/vendor/akinams053/seat-fitting` |
| Web URL | `http://ylxh.de` |
| APP_ENV | `local`，APP_DEBUG enabled |
| PHP | `8.4.21` |
| composer 安装的插件版本 | `akinams053/seat-fitting 1.3.0` (`source: tree/1.3.0`) |
| `FITTING_BYPASS_PERMISSIONS` | `true` —— 任何登录用户都能看 UI |

**认证方式**：`.creds.test` 第 4 行写 `scripts/test.key`，`ssh_seat.py` 看到含 `/` 自动走 key 认证。密钥文件 `scripts/test.key` 已在 `.gitignore`（`*.key`）覆盖，不会进 git。如果换机器，要把 `.creds.test`（host / port / user / `scripts/test.key`）和 `scripts/test.key` 同时同步过去，并 `chmod 600` 两个文件。

部署时走 composer，不要再 SFTP 直贴文件：

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

> **注意**：测试服上 `php artisan` 必须以 `sudo -u www-data` 运行；直接以 root 跑会让 psysh / config 缓存写到 `/var/www/.config/...` 触发权限错误。`tinker` 同理 —— 用 `--execute` 单行也不行，因为 psysh 仍要写 cache。要在 SSH 跑 PHP 探查脚本就改用 `artisan` 自定义命令或临时写个 route。

切换 `FITTING_BYPASS_PERMISSIONS` 后必须 `php artisan config:clear`，否则 Laravel 的 config cache 让新值不生效。

---

## 10. 生产服

通过 `scripts/ssh-seat 'cmd'`（默认读 `.creds`）。

生产规则（**严格**）：

- **任何写操作**（migrate / publish / queue restart / composer / 文件修改）必须事先和用户确认。
- `FITTING_BYPASS_PERMISSIONS` **不要设置**，或显式 `=false`。这是 staging-only 开关。
- 验证一次：

```bash
scripts/ssh-seat 'cd /var/www/seat && grep FITTING_BYPASS_PERMISSIONS .env || echo "(unset = safe default)"'
```

输出应是 `(unset = safe default)` 或 `FITTING_BYPASS_PERMISSIONS=false`。看到 `=true` 立刻提醒用户并问要不要改。

- 1.3.0 引入了新表（3 张），所以**生产升级到 1.3.0 必须跑 migrate**。`composer update` 不会自动迁移。
- 如果生产仍装的是上游 `cryptatech/seat-fitting`，要先评估替换路径和数据保留（namespace、DB 前缀、route name 全部沿用上游正是为了这种切换）。**不要**直接 `composer remove cryptatech/seat-fitting` 或删 vendor 目录，除非用户明确确认。
- 读类命令（`composer show`、`route:list`、`migrate:status`）可以直接跑。

---

## 11. 权限旁路开关（FITTING_BYPASS_PERMISSIONS）

`1.2.5` 起插件支持环境变量 `FITTING_BYPASS_PERMISSIONS`。

| 值 | 行为 |
|---|---|
| `true` | 服务提供者注册 `Gate::before` 钩子，所有 `fitting.*` 权限直接放行。任何登录 SeAT 的用户都能访问个人配装检查 / 配装及技能管理 / 配装分组 / 军团技能检查 |
| `false`（默认） | 走 SeAT 标准角色权限，未配权限的用户被路由层 `can:fitting.*` 中间件拦截 |

实现路径：`src/Config/fitting.config.php` 暴露 `bypass_permissions`；`FittingServiceProvider::registerPermissionBypass()` 在 boot 时检查并注册 `Gate::before`。

测试服开启、生产服关闭，详见 §9 / §10。

为什么这个开关存在：测试服上还没给每个测试账户加 SeAT 角色，又希望军团成员能直接打开 UI 走查。生产服角色配置齐全，必须强制按权限放行避免越权查看其他人配装/技能。这是单点 staging-only escape hatch，**不要**靠它做生产场景的"允许所有军团成员看" —— 那是 SeAT 角色配置的事。

---

## 12. 操作纪律

- **不要提交**：`.creds`、`.creds.test`、`scripts/test.key`、任何 `*.key` / `*.pem` —— `.gitignore` 已盖住，但执行 `git add -A` / `git add .` 时仍要警觉。
- **不要重命名** namespace / route name / DB 前缀到新 vendor 名，会破坏上游兼容路径。
- **修改 JS / CSS 后**，宿主 SeAT 必须 `php artisan vendor:publish --force --provider="CryptaTech\\Seat\\Fitting\\FittingServiceProvider"` 才能在 `public/web/{js,css}` 看到新文件。
- **本机没有 PHP**：PHP lint 要在测试服或宿主 SeAT 跑（`find vendor/akinams053/seat-fitting/src -name "*.php" -print0 | xargs -0 -n1 php -l`）。JS 可以本机 `node --check`。
- **CI**：`.github/workflows/lint.yml` 在每次 push 后跑 `pint` 并 auto-commit `Fixes coding style`，本地最好 push 前 `pint` 一遍避开多出来一个机器 commit。1.3.0 push 时 CI 没改东西，说明手写的格式已合规，不过这不是常态。
- **新增「删除目标」路径必须清 attachments**：见 §7 polymorphic 章节。
- **测试服可以自由验证；生产写操作必须确认。**

---

## 13. 已知未完成 / 后续候选方向

- **舰队审查（fleet review）功能未实现**：`fitting.fleet_review` 权限只声明、无路由、无控制器、无视图。
- **预留权限未接入**：`fitting.manage` 和 `fitting.corporation_report` 同样只声明、未被任何路由 `can:` 中间件使用。要么把现有路由迁过去做更细粒度的访问控制，要么从 `fitting.permissions.php` 里删掉，避免迷惑后来人。
- **composer.json 不锁 PHP 版本**：见 §3 末。
- **`composer.lock` 不进 git**（`.gitignore` 已盖住），所以依赖解析结果靠宿主决定 —— 这是 SeAT 插件的惯例，不要修改。
- **`effectiveRequirementsForTier` 在 corp report 里有潜在 N+1**：当 doctrine 含 N 个 fitting 时会逐 fit 查 plans + doctrines，464 人 × 2 fittings 量级目前没有观测到慢，但如果未来 fitting 数变多可以预取整个 doctrine 的 plans 一次性发出去。
- **方案管理 UI 与 doctrine 工作台的方案池现在是两份独立的 UI**：管理页用 Sortable 渲染、分组工作台用另一份 Sortable 渲染。文案和卡片样式有共享 CSS 但 JS 没抽公共组件，下一次大改可以合并。
- **配装复制不带分组归属**：意图行为（避免一键复制后污染分组），但如果用户期望「连分组也复制」需要单独决策。1.3.0 选了不带。

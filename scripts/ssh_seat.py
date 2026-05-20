#!/usr/bin/env python3
"""SSH helper: 读项目根 .creds 文件，登录 SeAT 服务器执行命令。

.creds 格式：4 个有效字段（host / port / user / password-or-keypath），各占一行；
            注释行（# 开头）和空白行被忽略。
            第 4 行如果含 `/` 或以 `~` 开头 → 当私钥路径，走 key 认证；
                          否则 → 当密码，走密码认证。
            相对路径以项目根（.creds 所在目录）为基准。

用法:
    python ssh_seat.py 'shell command'              # 读 .creds (默认)
    python ssh_seat.py -t test 'shell command'      # 读 .creds.test

退出码: 透传远程命令的退出码；本地错误用 1/2。
"""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

try:
    import paramiko
except ImportError:
    sys.stderr.write(
        "ERROR: 缺少依赖 paramiko\n"
        "  python -m pip install --user paramiko\n"
    )
    sys.exit(1)


def read_creds(creds_path: Path) -> tuple[str, int, str, str]:
    if not creds_path.exists():
        # 模板始终叫 .creds.example，与 target 无关
        example = creds_path.parent / ".creds.example"
        sys.stderr.write(
            f"ERROR: 凭据文件不存在: {creds_path}\n"
            f"请复制模板并编辑：\n"
            f"  cp {example} {creds_path}\n"
        )
        sys.exit(1)

    fields: list[str] = []
    for raw in creds_path.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        fields.append(line)

    if len(fields) < 4:
        sys.stderr.write(
            f"ERROR: .creds 有效字段不足 4 个（找到 {len(fields)} 个）\n"
            f"需要 4 个非注释非空行：host / port / user / password 各占一行\n"
        )
        sys.exit(1)

    host, port_s, user, password = fields[:4]
    for label, val in (("HOST", host), ("PORT", port_s), ("USER", user), ("PASS", password)):
        if val.startswith("REPLACE_ME_"):
            sys.stderr.write(f"ERROR: .creds 中字段 {label} 还是占位符 ({val})\n")
            sys.exit(1)
    try:
        port = int(port_s)
    except ValueError:
        sys.stderr.write(f"ERROR: .creds PORT 字段不是整数: {port_s!r}\n")
        sys.exit(1)
    return host, port, user, password


def main() -> None:
    parser = argparse.ArgumentParser(
        description="SSH helper: 用 .creds[.<target>] 密码登录远程并执行命令。",
    )
    parser.add_argument(
        "-t", "--target", default="",
        help="选择凭据文件后缀，例如 -t test 读 .creds.test；默认读 .creds",
    )
    parser.add_argument(
        "command", nargs="+",
        help="要在远程执行的 shell 命令（多个 token 会以空格 join）",
    )
    args = parser.parse_args()
    cmd = " ".join(args.command)

    script_dir = Path(__file__).resolve().parent
    project_root = script_dir.parent
    creds_name = ".creds" + (f".{args.target}" if args.target else "")
    creds = project_root / creds_name
    host, port, user, secret = read_creds(creds)

    connect_kwargs = dict(
        hostname=host, port=port, username=user,
        timeout=15, allow_agent=False, look_for_keys=False,
    )
    if "/" in secret or secret.startswith("~"):
        key_path = Path(secret).expanduser()
        if not key_path.is_absolute():
            key_path = project_root / key_path
        if not key_path.exists():
            sys.stderr.write(f"ERROR: 私钥文件不存在: {key_path}\n")
            sys.exit(1)
        connect_kwargs["key_filename"] = str(key_path)
    else:
        connect_kwargs["password"] = secret

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    try:
        client.connect(**connect_kwargs)
    except Exception as e:
        sys.stderr.write(f"ERROR: SSH 连接失败: {e}\n")
        sys.exit(1)

    try:
        _stdin, stdout, stderr = client.exec_command(cmd, timeout=120, get_pty=False)
        for chunk in iter(lambda: stdout.read(4096), b""):
            sys.stdout.buffer.write(chunk)
            sys.stdout.buffer.flush()
        err = stderr.read()
        if err:
            sys.stderr.buffer.write(err)
            sys.stderr.buffer.flush()
        rc = stdout.channel.recv_exit_status()
    finally:
        client.close()
    sys.exit(rc)


if __name__ == "__main__":
    main()

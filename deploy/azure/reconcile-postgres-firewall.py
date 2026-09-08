#!/usr/bin/env python3
"""Plan (default), check, or explicitly apply exact IPv4 rules for the existing ACA environment."""
import argparse
import ipaddress
import json
import subprocess
import sys


def public_ip(value):
    address = ipaddress.ip_address(value)
    if address.version != 4 or not address.is_global:
        raise ValueError('Only explicit public IPv4 addresses may be allowed')
    return str(address)


def az(*args):
    result = subprocess.run(['az', *args, '--output', 'json'], capture_output=True, text=True, check=True)
    return json.loads(result.stdout or 'null')


def desired_rules(addresses):
    return {'kmp-egress-' + ip.replace('.', '-'): ip
            for ip in sorted({public_ip(value) for value in addresses})}


def plan(existing, desired, retire):
    by_name = {rule['name']: rule for rule in existing}
    additions = [dict(name=name, address=ip) for name, ip in desired.items()
                 if name not in by_name or by_name[name]['startIpAddress'] != ip or by_name[name]['endIpAddress'] != ip]
    removals = [name for name in retire if name in by_name and name not in desired]
    stale = [name for name in by_name if name.startswith('kmp-egress-') and name not in desired]
    broad = [rule['name'] for rule in existing
             if rule['startIpAddress'] == '0.0.0.0' or rule['endIpAddress'] == '255.255.255.255']
    return additions, removals, stale, broad


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--resource-group', required=True)
    parser.add_argument('--web-app')
    parser.add_argument('--pre-runtime', action='store_true', help='Explicit infrastructure-only phase: discover jobs before web exists')
    parser.add_argument('--job', action='append', default=[])
    parser.add_argument('--consumer-app', action='append', default=[], metavar='RG/APP')
    parser.add_argument('--postgres-resource-group', required=True)
    parser.add_argument('--postgres-server', required=True)
    parser.add_argument('--allow-client-ip', action='append', default=[])
    parser.add_argument('--retire-rule', action='append', default=[])
    parser.add_argument('--expected-retention-days', type=int, default=35)
    action = parser.add_mutually_exclusive_group()
    action.add_argument('--check', action='store_true')
    action.add_argument('--apply', action='store_true')
    args = parser.parse_args()
    addresses = []
    if not args.web_app and not args.pre_runtime:
        raise ValueError('Web discovery is required outside the explicit pre-runtime phase')
    if args.pre_runtime and args.check:
        raise ValueError('A complete runtime drift check must include the web app')
    consumers = [(args.resource_group, args.web_app)] if args.web_app else []
    for consumer in args.consumer_app:
        pieces = consumer.split('/')
        if len(pieces) != 2 or not all(pieces):
            raise ValueError('Additional consumers must use RG/APP syntax')
        consumers.append(tuple(pieces))
    for group, app in consumers:
        ips = az('containerapp', 'show', '-g', group, '-n', app, '--query', 'properties.outboundIpAddresses')
        if not isinstance(ips, list) or not ips:
            raise ValueError('A required app has no reported outbound addresses; refusing an incomplete allowlist')
        addresses.extend(ips)
    jobs = az('containerapp', 'job', 'list', '-g', args.resource_group,
              '--query', '[].{name:name,ips:properties.outboundIpAddresses}')
    requested = set(args.job) if args.job else {job['name'] for job in jobs}
    if not requested:
        raise ValueError('No required jobs were discovered')
    found = set()
    for job in jobs:
        if job['name'] in requested:
            found.add(job['name'])
            if not isinstance(job['ips'], list) or not job['ips']:
                raise ValueError('A required job has no reported outbound addresses')
            addresses.extend(job['ips'])
    if found != requested:
        raise ValueError('A required database job was not found')
    desired = desired_rules(addresses + args.allow_client_ip)
    existing = az('postgres', 'flexible-server', 'firewall-rule', 'list',
                  '-g', args.postgres_resource_group, '-n', args.postgres_server)
    changes = plan(existing, desired, args.retire_rule)
    additions, removals, stale, broad = changes
    retention = az('postgres', 'flexible-server', 'show', '-g', args.postgres_resource_group,
                   '-n', args.postgres_server, '--query', 'backup.backupRetentionDays')
    print(json.dumps({'desiredAddressCount': len(desired), 'additions': additions, 'desiredRules': desired,
                      'explicitRuleRemovals': removals, 'staleManagedRulesRequireReview': stale,
                      'broadRulesRequireReview': broad, 'retentionDays': retention,
                      'expectedRetentionDays': args.expected_retention_days}, indent=2))
    if args.apply:
        # Add before retiring; never auto-delete unknown, previous-revision or operator rules.
        for rule in additions:
            az('postgres', 'flexible-server', 'firewall-rule', 'create', '-g', args.postgres_resource_group,
               '-n', args.postgres_server, '--rule-name', rule['name'], '--start-ip-address', rule['address'],
               '--end-ip-address', rule['address'])
        for name in removals:
            az('postgres', 'flexible-server', 'firewall-rule', 'delete', '-g', args.postgres_resource_group,
               '-n', args.postgres_server, '--rule-name', name, '--yes')
        print('Requested firewall changes applied. Verify every consumer before retiring additional rules.')
    elif args.check and (additions or stale or broad or retention != args.expected_retention_days):
        return 1
    return 0


if __name__ == '__main__':
    try:
        sys.exit(main())
    except (ValueError, KeyError, OSError, subprocess.SubprocessError):
        print('Firewall discovery/reconciliation failed; no broad-access fallback is permitted.', file=sys.stderr)
        sys.exit(2)

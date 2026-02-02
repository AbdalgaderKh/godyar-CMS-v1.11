# GitHub Milestones & Issues for v1.25

## Milestone: v1.25.0-security-plus (P0)
- [ ] Implement TOTP 2FA verification step with backup codes
- [ ] Add admin UI for enable/disable/regenerate backup codes
- [ ] Add migrations for twofa_backup_codes + indices
- [ ] Add rate limit bucket for 2FA verify
- [ ] Add docs: SECURITY.md 2FA section
- [ ] Add tests: totp verify, backup consume

## Milestone: v1.25.0-dx (P1)
- [ ] Add docs: INSTALLATION/ARCHITECTURE/CODING_STANDARDS/CONTRIBUTING/PLUGINS/API
- [ ] Add GitHub issue templates + PR template
- [ ] Add local dev scripts (lint, cache clear, diag)

## Milestone: v1.25.0-performance (P2)
- [ ] Cache driver abstraction (file/apcu/redis)
- [ ] Add query profiling toggle
- [ ] Optimize search/count queries

## Milestone: v1.25.0-cleanup (P2)
- [ ] Reduce duplication in legacy controllers
- [ ] Unify naming + folder layout

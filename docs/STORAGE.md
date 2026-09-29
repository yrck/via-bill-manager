# PostgreSQL and TimescaleDB

Decision for the first increment: standard PostgreSQL 17 for the local foundation; do not install an unused time-series dependency yet.

Use regular relational tables for organizations, properties, accounts, invoices, line items, review findings, documents, contracts and audit events. Correct composite indexes, bounded queries, permissions and precomputed summaries matter more here than partitioning each table by time.

When interval ingestion begins, enable TimescaleDB on a compatible PostgreSQL deployment. Use hypertables for meter readings partitioned by observation time, with explicit organization, meter, unit, source and quality fields. Deduplication keys must include the partitioning time column; do not blindly reuse an id-only primary key. Keep corrections, timestamp semantics, UTC/local timezone conversion and DST handling explicit. Consider time-bucket aggregates for hourly/daily analysis and retention only after agreeing source-data retention requirements.

Performance is workload-dependent: benchmark ingestion and representative meter/date/organization queries, inspect query plans and select chunk intervals/indexes from measured volume. Do not claim automatic speedups for invoice workloads. Validate extension/Postgres version support, architecture, backups and restore before deployment.

Official references reviewed September 29, 2026:
- https://www.tigerdata.com/docs/learn/hypertables/understand-hypertables
- https://www.tigerdata.com/docs/learn/tutorials/analyze-energy-consumption
- https://github.com/timescale/Tiger-Data-Docs/blob/main/src/content/docs/build/performance-optimization/hypertables-and-unique-indexes.mdx

Timescale is planned, not installed or benchmarked in this increment.

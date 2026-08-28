# Production execution workflow checklist

- [x] Release CS creates one requisition from its BOM in one transaction.
- [x] Issue allows configured over-issue tolerance and completes requisitions.
- [x] MPS validates material readiness/ETA before scheduling.
- [x] Shop Floor prevents output above the preceding operation and computes pipeline WIP.
- [x] Closing an order snapshots cost and profit in `order_costings`.
- [x] Verify routes, migrations, syntax, and tests.

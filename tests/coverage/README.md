# Coverage inventory

This inventory records shipped declarations and test gaps. It is not a claim that the release is ready.

From Free's plugin root:

```bash
python3 tests/coverage/check.py
python3 tests/coverage/check.py --write-roadmap
python3 tests/coverage/check.py --require-journeys
```

The first command checks inventory structure, declaration drift, stable IDs, runner references and generated roadmap consistency. The second regenerates both readable roadmaps. The third fails until native authoring journeys are implemented; it does not yet prove those tests passed for a release artifact.

`catalog.py` reads explicit shipped declarations without booting WordPress. Taxonomy families use `{taxonomy}`. Unsupported identifier expressions fail explicitly. Native registry snapshots and executable case discovery are additional work in the change plan; static inventory checks cannot establish semantic coverage.

Free owns the schema/checker. Pro owns `tests/coverage/inventory.json` and its generated roadmap and uses the declared sibling Free harness. Free-only CI can pass `--owner elemacy`. New features and renamed test references must update the inventory in the same change.

Existing runners have stable IDs and recorded layers. Feature-level `coverage` references start empty until the actual case assertions are reviewed; do not infer coverage merely because a suite name sounds related. Pending cases remain pending. The initial baseline is in the root OpenSpec change's `evidence/BASELINE.md`.

## Named gaps

- Genesis and BB Theme are premium themes outside the pinned real-theme set, so `GenesisCompatibility` and `BBThemeCompatibility` are verified only by source checks. That is not real-theme coverage and must not be reported as such.
- `elemacy:integration:elementor-pro-coexistence` runs only in the Elementor Pro profile (`setup.py --elementor-pro-plugin`, a paid plugin the owner provides). Its skip in every other profile is expected; the Elementor Pro profile run is what counts.

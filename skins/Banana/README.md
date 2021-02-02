# Banana skin

## Notes

* The `Flow` extension uses a 2-pane layout for wide screens. But we always have a narrow container and we don't want the 2-pane layout. To fix it, add the following lines to `/extensions/Flow/modules/styles/flow.variables.less`:
    ``` less
    @medium: 1000000px;
    @large: 1000000px;
    @xlarge: 1000000px;
    ```
    This needs to be done every time we update `Flow`.

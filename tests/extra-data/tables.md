Simple | Table
------ | -----
1      | 2
3      | 4

| Simple | Table |
| ------ | ----- |
| 1      | 2     |
| 3      | 4     |
| 3      | 4     \|
| 3      | 4    \\|

Check https://github.com/erusev/parsedown/issues/184 for the following:

Foo | Bar | State
------ | ------ | -----
`Code | Pipe` | Broken | Blank
`Escaped Code \| Pipe` | Broken | Blank
Escaped \| Pipe | Broken | Blank
Escaped \\| Pipe | Broken | Blank
Escaped \\ | Pipe | Broken | Blank

| Simple | Table |
| ------ | ----- |
| 3      | 4     |
3      | 4

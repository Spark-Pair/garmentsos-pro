---- for generating commit throw chatgpt ----

git status --short > changes-report.txt
git log --oneline --decorate --all -20 >> changes-report.txt
git diff --name-status v1.9.12...HEAD >> changes-report.txt
git diff v1.9.12...HEAD >> changes-report.txt
git diff >> changes-report.txt
git diff --cached >> changes-report.txt

---- ------

git diff (git describe --tags --abbrev=0)..HEAD > unreleased-changes.txt

---- -----
## ChangeLog for IncidentReporting

### 1.1.8 (07-08-2022)
* Fix hide-if values to avoid DomainException

### 1.1.7 (29-06-2022)
* Require MediaWiki 1.38.0
* Modernise IncidentReportingOOUIForm

### 1.1.6 (14-06-2021)
* Require MediaWiki 1.36.0
* DB_MASTER -> DB_PRIMARY

### 1.1.5 (17-03-2021)
* Introduce stats for IncidentReports

### 1.1.4 (12-03-2021)
* add license-name

### 1.1.3 (30-03-2020)
* Fix typo in class name.

### 1.1.2 (21-03-2020)
* Display log events based on timestamp not on added form order (by ID)
* Correct log link from $id/edit to just $id
* Convert to ConfigRegistry

### 1.1.1 (15-01-2020)
* Replace deprecated OutputPage::parse(), which will be removed from MW

### 1.1.0 (22-12-2019)
* Add support for MediaWiki 1.34

### 1.0.3 (07-09-2019)
* Round $outageTotal and $outageVisible

### 1.0.2 (28-02-2019)
* Fix minor log errors.

### 1.0.1 (19-02-2019)
* Fix Exception for non-existent incidents.
* Add other box for useful information.

### 1.0.0 (16-02-2019)
* Initial commit of code.

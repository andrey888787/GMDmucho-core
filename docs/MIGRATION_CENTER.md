# Migration Center

MuchoCore includes a guided database migration flow so moving an existing GDPS does not require manually rewriting SQL.

## Supported source family

The first automatic importer targets the Cvolton database schema. GDPS-Maker is detected as **Cvolton / GDPS-Maker compatible** when its database exposes the same schema. Detection is schema-based rather than dependent on a project name.

Required source tables:

- `accounts`
- `users`
- `levels`

Optional tables:

- `levelscores`
- `platscores`

## Using the wizard

From the MuchoCore VPS:

```bash
sudo mucho
```

Open:

```text
Database & migrations
→ Migration Center
```

The wizard asks for the old database connection, detects the schema, and shows a preview before importing anything.

The default path is always a dry-run. To apply the import, the interactive wizard requires typing:

```text
MIGRATE
```

The source connection is opened with a read-only transaction. MuchoCore records source-to-target account and level mappings, so repeated runs can resume/reconcile previously imported rows rather than blindly creating duplicates.

## Password handling

Passwords are not assumed to be portable between unrelated hashing schemes. When a source password is not already a password hash that MuchoCore can verify safely, the imported account receives a random unusable password hash and is marked for a password reset workflow.

The source `gjp2` value is only retained as a hash when it matches the expected legacy format.

## Safety model

Before a production migration:

1. make a MuchoCore database backup;
2. run the dry-run preview;
3. inspect account/level/score counts;
4. apply the migration;
5. run `sudo mucho` → **Mucho Doctor** and verify the public `/health` endpoint;
6. keep the old GDPS database untouched until the new instance is verified.

Unknown schemas are reported and rejected rather than guessed.

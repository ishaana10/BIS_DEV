-- Run in phpMyAdmin (SQL tab) on a quiet moment. Each statement is independent.
-- 1) Row-level locking for the nuBuilder system tables (all are MyISAM = table locks).
ALTER TABLE zzzzsys_session ENGINE=InnoDB;
ALTER TABLE zzzzsys_php     ENGINE=InnoDB;
ALTER TABLE zzzzsys_form    ENGINE=InnoDB;
ALTER TABLE zzzzsys_object  ENGINE=InnoDB;
ALTER TABLE zzzzsys_browse  ENGINE=InnoDB;
ALTER TABLE zzzzsys_tab     ENGINE=InnoDB;
ALTER TABLE zzzzsys_event   ENGINE=InnoDB;
ALTER TABLE zzzzsys_config  ENGINE=InnoDB;
ALTER TABLE zzzzsys_setup   ENGINE=InnoDB;
-- 2) Missing index: nuProcedure()/nuEval() look procedures up by code on every hook.
ALTER TABLE zzzzsys_php ADD INDEX idx_sph_code (sph_code);

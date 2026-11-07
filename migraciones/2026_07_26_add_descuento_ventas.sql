-- Migración: agrega soporte de descuentos a la tabla `ventas`
-- Ejecutar una sola vez en la base de datos `bd_spectre`.
--
-- `subtotal`  -> suma de los ítems (productos/servicios) ANTES del descuento
-- `descuento` -> monto de descuento aplicado (en soles), ya calculado
-- `total`     -> se mantiene igual que antes: el monto FINAL a pagar (subtotal - descuento)
--
-- Esto es retro-compatible: todas las consultas existentes que usan `v.total`
-- siguen funcionando igual, porque `total` sigue siendo el monto final pagado.

ALTER TABLE ventas
    ADD COLUMN subtotal DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER total,
    ADD COLUMN descuento DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER subtotal,
    ADD COLUMN descuento_tipo ENUM('ninguno','porcentaje','monto') NOT NULL DEFAULT 'ninguno' AFTER descuento;

-- Para las ventas ya existentes, dejamos subtotal = total (sin descuento retroactivo)
UPDATE ventas SET subtotal = total WHERE subtotal = 0;

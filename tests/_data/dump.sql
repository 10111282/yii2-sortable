
CREATE TABLE cartoons (
  id SERIAL PRIMARY KEY NOT NULL,
  title CHARACTER VARYING(100) NOT NULL,
  category_id INTEGER NOT NULL,
  sort_local INTEGER NOT NULL,
  sort_general INTEGER NOT NULL,
  archived BOOLEAN DEFAULT false,
  color BOOLEAN DEFAULT false
);

INSERT INTO cartoons(title, category_id, sort_local, sort_general, archived, color) VALUES
('Fiddlesticks', 14, 1000, 7000, true, true),
('Trolley Troubles,', 14, 2000, 8000, false, false),
('Fantasmagorie', 14, 3000, 9000, true, false),

('Winnie the pooh', 15, 3000, 3000, false, true),
('Kolobok (The loaf)', 15, 1000, 2000, false, true),
('Hedgehog in the fog', 15, 2000, 1000, false, true),

('South Park', 16, 1000, 4000, false, true),
('Futurama', 16, 2000, 5000, false, true),
('Rick and Morty', 16, 3000, 6000, false, true),

('Zero', 0, 1000, 9000, false, true);




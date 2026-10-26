CREATE TABLE treinador (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    saldo INT NOT NULL DEFAULT 1000
);

CREATE TABLE pokemon (
    id INT AUTO_INCREMENT PRIMARY KEY,
    treinador_id INT NOT NULL,
    nome VARCHAR(50) NOT NULL,
    hp INT NOT NULL,
    FOREIGN KEY (treinador_id) REFERENCES treinador(id)
);

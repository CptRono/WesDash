CREATE TABLE users (
    username   VARCHAR(255) PRIMARY KEY,
    password   VARCHAR(255) NOT NULL,
    is_deleted TINYINT(1)   NOT NULL DEFAULT 0,
    role       ENUM('User','Dasher') NOT NULL DEFAULT 'User',
    balance    INT          NOT NULL DEFAULT 0     
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE requests (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    username          VARCHAR(255)            NOT NULL,
    item              VARCHAR(255)            NOT NULL,
    quantity          INT                     NOT NULL DEFAULT 1,
    is_custom         TINYINT(1)              NOT NULL DEFAULT 0,       
    est_price         DECIMAL(10,2)                    DEFAULT NULL,    
    purchase_mode     VARCHAR(255)                     DEFAULT NULL,   
    drop_off_location VARCHAR(255)            NOT NULL,
    delivery_speed    ENUM('urgent','common') NOT NULL DEFAULT 'common',
    status            ENUM('pending','accepted','completed','confirmed')
                                         NOT NULL DEFAULT 'pending',
    accepted_by       VARCHAR(255)                     DEFAULT NULL,   
    total_price       DECIMAL(10,2)           NOT NULL DEFAULT 0.00,    
    real_price        DECIMAL(10,2)                    DEFAULT NULL,     
    receipt_photo     VARCHAR(255)                     DEFAULT NULL,   
    created_at        DATETIME               NOT NULL DEFAULT CURRENT_TIMESTAMP,
    review_prompt_status VARCHAR(50)          DEFAULT 'pending',
    CONSTRAINT fk_requests_user
        FOREIGN KEY (username)
        REFERENCES users(username) ON DELETE CASCADE,

    INDEX idx_status   (status),
    INDEX idx_dasher   (accepted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE chat_rooms (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    order_id    INT          NOT NULL,
    user_name   VARCHAR(255) NOT NULL,
    dasher_name VARCHAR(255) NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at   DATETIME     NULL,
    INDEX(order_id),
    CONSTRAINT fk_room_req
      FOREIGN KEY (order_id) REFERENCES requests(id)
      ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE chat_messages (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    room_id  INT          NOT NULL,
    sender   VARCHAR(255) NOT NULL,
    message  TEXT         NOT NULL,
    sent_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_room_time (room_id, sent_at),
    CONSTRAINT fk_msg_room
      FOREIGN KEY (room_id) REFERENCES chat_rooms(id)
      ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tips (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT  NOT NULL,
    amount     INT  NOT NULL,        
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_tip_req
      FOREIGN KEY (request_id) REFERENCES requests(id)
      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE recharges (
id          INT AUTO_INCREMENT PRIMARY KEY,
username    VARCHAR(255)   NOT NULL,
amount      INT            NOT NULL,        
stripe_pi   VARCHAR(255)   NULL,            
status      ENUM('pending','succeeded','failed')
NOT NULL DEFAULT 'pending',
created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
CONSTRAINT fk_recharge_user FOREIGN KEY (username)
REFERENCES users(username) ON DELETE CASCADE
) ENGINE=InnoDB CHARSET=utf8mb4;

CREATE TABLE reviews (
id           INT(11)       NOT NULL AUTO_INCREMENT,
order_id     INT(11)       NOT NULL,
review_text  TEXT           CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
rating       INT(11)       NOT NULL,
created_at   DATETIME      NOT NULL,
PRIMARY KEY (id),
INDEX idx_order_id (order_id)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;
<?php

return static function (PDO $pdo): void {
    // Existing answers count as evidence too. Preserve their dates and immutable text snapshots.
    $pdo->exec("INSERT IGNORE INTO neo_learning_events
        (user_id,conteudo_id,materia_id,questao_id,event_key,enunciado,resposta,correta,acertou,dificuldade,
         tentativa,formato,habilidade,tipo_questao,tipo_erro,explicacao,revisar_em,criado_em)
        SELECT h.user_id,h.conteudo_id,c.materia_id,q.id,CONCAT('historico:',r.id),r.enunciado_snapshot,
               UPPER(r.resposta_usuario),UPPER(r.resposta_correta),UPPER(r.resposta_usuario)=UPPER(r.resposta_correta),
               GREATEST(1,LEAST(12,COALESCE(q.dificuldade,h.dificuldade,1))),1,'questoes',q.habilidade,
               COALESCE(q.tipo_questao,'multipla_escolha'),'nao_classificado',r.feedback,
               CASE WHEN UPPER(r.resposta_usuario)<>UPPER(r.resposta_correta) THEN DATE_ADD(DATE(h.data),INTERVAL 1 DAY) ELSE NULL END,h.data
        FROM respostas_historico r
        JOIN historico h ON h.id=r.historico_id
        JOIN conteudos c ON c.id=h.conteudo_id AND c.user_id=h.user_id
        LEFT JOIN questoes q ON q.id=r.questao_id AND q.user_id=h.user_id AND q.conteudo_id=h.conteudo_id
        WHERE UPPER(r.resposta_usuario) IN ('A','B','C','D') AND UPPER(r.resposta_correta) IN ('A','B','C','D')");
};
